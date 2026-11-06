<?php
declare(strict_types=1);
namespace App\Modules\Learning\Services;
use App\Core\ApiException;
use App\Modules\Learning\Repositories\LearningRepository;
use App\Shared\{Cefr,HeartRules};
use PDO;

/** Account-scoped, persisted mistake review. No XP; at most one heart per round. */
final class HeartPracticeService
{
    private LearningRepository $repo;
    public function __construct(private PDO $db) { $this->repo=new LearningRepository($db); }
    private function rows(string $sql,array $args=[]): array { $q=$this->db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC); }
    private function write(string $sql,array $args=[]): void { $this->db->prepare($sql)->execute($args); }
    private function user(int $id): array
    {
        if(!Cefr::installed($this->db)) { throw new ApiException('UPGRADE_REQUIRED','Cần chạy bản cập nhật CSDL 006 trước.',409); }
        $user=$this->repo->lockUser($id);
        if($user['status']!=='active') { throw new ApiException('FORBIDDEN','Tài khoản không hoạt động.',403); }
        $hp=HeartRules::refresh($user,new \DateTimeImmutable('now',new \DateTimeZone('UTC')));
        $this->repo->saveHearts($id,$hp);return array_replace($user,$hp);
    }
    private function pending(int $user): array
    {
        return $this->rows('SELECT m.*,w.english,w.vietnamese,w.cefr_level,w.topic_id,q.exercise_id
            FROM learning_mistakes m JOIN words w ON w.id=m.word_id JOIN topics t ON t.id=w.topic_id
            JOIN session_questions q ON q.id=m.last_question_id
            WHERE m.user_id=? AND m.resolved_at IS NULL AND w.is_active=1 AND t.is_active=1 ORDER BY m.last_wrong_at,m.word_id',[$user]);
    }
    private function active(int $user): ?array
    {
        return $this->rows("SELECT * FROM learning_sessions WHERE user_id=? AND mode='heart_practice' AND status='in_progress' ORDER BY id DESC LIMIT 1",[$user])[0]??null;
    }
    public function summary(int $id): array
    {
        return $this->repo->transaction(function() use($id): array {
            $user=$this->user($id);$active=$this->active($id);
            return ['current_hp'=>(int)$user['current_hp'],'max_hp'=>HeartRules::MAX_HP,'next_hp_at'=>$user['next_hp_at'],
                'server_now'=>gmdate('Y-m-d\TH:i:s\Z'),'regen_minutes'=>HeartRules::REGEN_MINUTES,
                'mistakes'=>array_map(static fn($w)=>['word_id'=>(int)$w['word_id'],'english'=>$w['english'],'vietnamese'=>$w['vietnamese'],'cefr_level'=>$w['cefr_level'],'wrong_count'=>(int)$w['wrong_count']],$this->pending($id)),
                'active_session'=>$active===null?null:$this->state($user,$active)];
        });
    }
    public function start(int $id): array
    {
        return $this->repo->transaction(function() use($id): array {
            $user=$this->user($id);$active=$this->active($id);
            if($active!==null) { return $this->state($user,$active); }
            $pending=array_slice($this->pending($id),0,3);
            if($pending===[]) { throw new ApiException('NO_MISTAKES','Bạn chưa có từ sai cần ôn.',409); }
            $session=$this->repo->createSession($id,null,count($pending),['heart_reward'=>1,'no_heart_loss'=>true], 'heart_practice');
            foreach($pending as $i=>$word) {
                // Recall the English word rather than revealing the answer in four choices.
                $accepted=[$word['english']];
                foreach($this->rows('SELECT english FROM words WHERE topic_id=? AND cefr_level=? AND vietnamese=? AND is_active=1',[$word['topic_id'],$word['cefr_level'],$word['vietnamese']]) as $synonym) { $accepted[]=$synonym['english']; }
                $snapshot=['type'=>'translate_text','content'=>['instruction'=>'Viết lại từ tiếng Anh bạn đã trả lời sai','prompt'=>$word['vietnamese']],
                    'answer'=>['accepted_answers'=>array_values(array_unique($accepted)),'normalization'=>['trim'=>true,'case_sensitive'=>false]],'target_word_ids'=>[(int)$word['word_id']]];
                $this->repo->addQuestion($session,(int)$word['exercise_id'],'main',$i+1,json_encode($snapshot,JSON_THROW_ON_ERROR));
                $question=(int)$this->db->lastInsertId();
                $this->write('INSERT INTO heart_practice_items(session_id,word_id,mistake_question_id,practice_question_id) VALUES(?,?,?,?)',[$session,$word['word_id'],$word['last_question_id'],$question]);
            }
            return $this->state($user,$this->requireSession($id,$session));
        });
    }
    private function requireSession(int $user,int $id): array
    {
        $session=$this->repo->session($user,$id,true,'heart_practice');
        if($session===null) { throw new ApiException('SESSION_NOT_FOUND','Không tìm thấy lượt ôn từ sai.',404); }
        return $session;
    }
    public function answer(int $userId,int $id,array $body): array
    {
        $key=$body['request_key']??null;$questionId=$body['question_id']??null;
        if(!is_string($key)||!preg_match('/^[A-Za-z0-9:_-]{16,100}$/D',$key)||!is_int($questionId)||$questionId<1) { throw new ApiException('VALIDATION_FAILED','Câu trả lời không hợp lệ.',422); }
        return $this->repo->transaction(function() use($userId,$id,$body,$key,$questionId): array {
            $user=$this->user($userId);$session=$this->requireSession($userId,$id);$questions=$this->repo->questions($id);
            $question=null;$current=null;
            foreach($questions as $q) { if((int)$q['id']===$questionId) { $question=$q; } if($q['resolved_at']===null) { $current??=$q; } }
            if($question===null) { throw new ApiException('QUESTION_NOT_FOUND','Câu hỏi không thuộc lượt ôn này.',404); }
            $snapshot=json_decode($question['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$grader=new ExerciseService();$answer=$grader->normalize($snapshot,$body['answer']??null);
            $prior=$this->repo->submission($key);
            if($prior!==null) {
                if((int)$prior['session_id']!==$id || (int)$prior['session_question_id']!==$questionId || json_decode($prior['answer_json'],true)!==$answer) { throw new ApiException('REQUEST_KEY_CONFLICT','Mã gửi lại đã dùng cho câu trả lời khác.',409); }
                return ['is_correct'=>(bool)$prior['is_correct'],'replayed'=>true,'session'=>$this->state($user,$session)];
            }
            if($session['status']!=='in_progress'||$current===null||(int)$current['id']!==$questionId) { throw new ApiException('QUESTION_OUT_OF_ORDER','Câu hỏi chưa đến lượt hoặc đã hoàn thành.',409); }
            $correct=$grader->grade($snapshot,$answer);$this->repo->recordAnswer($questionId,$key,$answer,$correct);
            if($correct) { $this->repo->resolveQuestion($questionId);$this->repo->addCorrect($id); }
            $remaining=array_filter($this->repo->questions($id),static fn($q)=>$q['resolved_at']===null);
            if($remaining===[]) {
                $added=min(1,HeartRules::MAX_HP-(int)$user['current_hp']);
                $hp=['current_hp'=>(int)$user['current_hp']+$added,'next_hp_at'=>(int)$user['current_hp']+$added===HeartRules::MAX_HP?null:$user['next_hp_at']];
                $this->repo->saveHearts($userId,$hp);$user=array_replace($user,$hp);
                $this->write('INSERT INTO heart_practice_rewards(session_id,user_id,hearts_added) VALUES(?,?,?)',[$id,$userId,$added]);
                foreach($this->rows('SELECT * FROM heart_practice_items WHERE session_id=?',[$id]) as $item) {
                    $this->write('UPDATE learning_mistakes SET resolved_at=UTC_TIMESTAMP() WHERE user_id=? AND word_id=? AND last_question_id=?',[$userId,$item['word_id'],$item['mistake_question_id']]);
                }
                $this->repo->finish($id,'completed');
            }
            return ['is_correct'=>$correct,'replayed'=>false,'session'=>$this->state($user,$this->requireSession($userId,$id))];
        });
    }
    private function state(array $user,array $session): array
    {
        $question=null;$answered=0;
        foreach($this->repo->questions((int)$session['id']) as $q) {
            if($q['resolved_at']!==null) { $answered++;continue; }
            if($question===null && $session['status']==='in_progress') {
                $snapshot=json_decode($q['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
                $question=['id'=>(int)$q['id'],'type'=>$snapshot['type'],'content'=>$snapshot['content']];
            }
        }
        $reward=$this->rows('SELECT hearts_added FROM heart_practice_rewards WHERE session_id=?',[$session['id']])[0]??null;
        return ['id'=>(int)$session['id'],'status'=>$session['status'],'total_questions'=>(int)$session['total_questions'], 'answered_main'=>$answered,
            'current_hp'=>(int)$user['current_hp'],'max_hp'=>HeartRules::MAX_HP,'next_hp_at'=>$user['next_hp_at'],'hearts_added'=>(int)($reward['hearts_added']??0),'question'=>$question];
    }
}
