<?php
declare(strict_types=1);
namespace App\Modules\Learning\Services;

use App\Core\ApiException;
use App\Modules\Learning\Repositories\{ChapterRepository,LearningRepository};

final class ChapterChallengeService
{
    public function __construct(private ChapterRepository $repo, private LearningRepository $learning, private ExerciseService $grader = new ExerciseService()) {}
    private function ready(): void
    {
        if (!$this->repo->installed()) throw new ApiException('SCHEMA_REQUIRED','Cần áp dụng migration 008 để học vượt chương.',409);
    }
    private function lock(int $userId): void
    {
        $user = $this->repo->lockUser($userId);
        if (($user['status'] ?? '') !== 'active') throw new ApiException('FORBIDDEN','Tài khoản không hoạt động.',403);
    }
    public function start(int $userId, int $topicId): array
    {
        $this->ready();
        $lessons = array_values(array_filter($this->learning->getActiveLessons(), static fn ($l) => (int)$l['topic_id'] === $topicId));
        $groups = [];
        foreach ($lessons as $lesson) {
            $words = $this->learning->getWordsByLessonId((int)$lesson['id']);
            if (count($words) < 2) continue;
            foreach ($this->learning->exercises((int)$lesson['id']) as $exercise) {
                if ($exercise['type'] === 'match_pairs') continue;
                $snapshot = $this->grader->snapshot($exercise, $words);
                if ($snapshot === null) continue;
                // A checkpoint measures recall without the optional learning hints.
                unset($snapshot['content']['hint_image']);
                $groups[(int)$lesson['level']][] = $snapshot;
            }
        }
        return $this->repo->transaction(function () use ($userId,$topicId,$groups) {
            $this->lock($userId);
            if (!$this->repo->rows('SELECT id FROM topics WHERE id=? AND is_active=1',[$topicId])) throw new ApiException('NOT_FOUND','Không tìm thấy chương.',404);
            $active = $this->repo->activeAttempt($userId,$topicId);
            if ($active !== null) return $this->state($active);
            $pool = []; $questions = [];
            foreach ($groups as $items) { shuffle($items); $questions[] = array_shift($items); $pool = array_merge($pool,$items); }
            shuffle($pool);
            $questions = array_slice(array_merge($questions,$pool),0,12);
            if (count($questions) < 6) throw new ApiException('CHAPTER_NOT_READY','Chương cần ít nhất 6 câu hỏi hợp lệ để học vượt.',409);
            shuffle($questions);
            foreach ($questions as &$q) foreach (['options','tokens'] as $key) if (isset($q['content'][$key])) shuffle($q['content'][$key]);
            unset($q);
            $id = $this->repo->create($userId,$topicId,$questions);
            return $this->state($this->repo->attempt($userId,$id));
        });
    }
    public function get(int $userId, int $id): array
    {
        $this->ready(); $attempt=$this->repo->attempt($userId,$id);
        if ($attempt === null) throw new ApiException('ATTEMPT_NOT_FOUND','Không tìm thấy lượt học vượt.',404);
        return $this->state($attempt);
    }
    public function answer(int $userId, int $id, array $body): array
    {
        $this->ready();
        $key=$body['request_key']??null; $number=$body['question_number']??null;
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9:_-]{16,100}$/D',$key) || !is_int($number) || $number<1) throw new ApiException('VALIDATION_FAILED','Câu hỏi hoặc mã gửi không hợp lệ.',422);
        return $this->repo->transaction(function () use ($userId,$id,$key,$number,$body) {
            $this->lock($userId); $attempt=$this->repo->attempt($userId,$id,true);
            if ($attempt === null) throw new ApiException('ATTEMPT_NOT_FOUND','Không tìm thấy lượt học vượt.',404);
            $questions=json_decode($attempt['questions_json'],true,512,JSON_THROW_ON_ERROR);
            $submissions=json_decode($attempt['submissions_json'],true,512,JSON_THROW_ON_ERROR);
            $q=$questions[$number-1]??null;
            if ($q===null) throw new ApiException('QUESTION_NOT_FOUND','Câu hỏi không thuộc lượt kiểm tra.',404);
            $answer=$this->grader->normalize($q,$body['answer']??null);
            if (isset($submissions[$key])) {
                $old=$submissions[$key];
                if ($old['number']!==$number || $old['answer']!==$answer) throw new ApiException('REQUEST_KEY_CONFLICT','Mã gửi đã được dùng cho đáp án khác.',409);
                return ['is_correct'=>$old['correct'],'replayed'=>true,'attempt'=>$this->state($attempt)];
            }
            if ($attempt['status']!=='in_progress' || (int)$attempt['current_index']!==$number-1) throw new ApiException('QUESTION_OUT_OF_ORDER','Câu hỏi chưa đến lượt hoặc đã trả lời.',409);
            $correct=$this->grader->grade($q,$answer);
            $submissions[$key]=['number'=>$number,'answer'=>$answer,'correct'=>$correct];
            $attempt['current_index']=(int)$attempt['current_index']+1;
            $attempt['correct_count']=(int)$attempt['correct_count']+(int)$correct;
            if ($attempt['current_index']===count($questions)) $attempt['status']=$attempt['correct_count']*100>=count($questions)*80?'completed':'failed';
            $this->repo->save($attempt,$submissions);
            return ['is_correct'=>$correct,'replayed'=>false,'attempt'=>$this->state($attempt)];
        });
    }
    private function state(array $attempt): array
    {
        $questions=json_decode($attempt['questions_json'],true,512,JSON_THROW_ON_ERROR);
        $q=$attempt['status']==='in_progress'?($questions[(int)$attempt['current_index']]??null):null;
        return ['id'=>(int)$attempt['id'],'topic_id'=>(int)$attempt['topic_id'],'status'=>$attempt['status'],
            'answered'=>(int)$attempt['current_index'],'total'=>count($questions),'correct_count'=>(int)$attempt['correct_count'],
            'pass_percent'=>80,'unlocked'=>$attempt['status']==='completed',
            'question'=>$q===null?null:['type'=>$q['type'],'content'=>$q['content'],'number'=>(int)$attempt['current_index']+1]];
    }
}
