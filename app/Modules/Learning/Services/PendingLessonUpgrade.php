<?php
declare(strict_types=1);
namespace App\Modules\Learning\Services;
use App\Modules\Learning\Repositories\LearningRepository;
use PDO;

/** Installation-only upgrade: keep answered history; replace only untouched main questions. */
final class PendingLessonUpgrade
{
    public function __construct(private PDO $db) {}
    private function rows(string $sql,array $args=[]): array { $s=$this->db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC); }
    public function all(): int
    {
        $repo=new LearningRepository($this->db);$count=0;$grader=new ExerciseService();
        foreach($this->rows("SELECT id,user_id,lesson_id FROM learning_sessions WHERE mode='lesson' AND status='in_progress' ORDER BY user_id,id") as $entry) {
            $count+=$repo->transaction(function() use($entry,$repo,$grader): int {
                $repo->lockUser((int)$entry['user_id']);$session=$repo->session((int)$entry['user_id'],(int)$entry['id'],true);
                if($session===null||$session['status']!=='in_progress') { return 0; }
                $rules=json_decode($session['rules_json'],true,512,JSON_THROW_ON_ERROR);
                if(($rules['question_bank_version']??'')==='mixed-cefr-v4') { return 0; }
                $pending=$this->rows("SELECT q.id FROM session_questions q WHERE q.session_id=? AND q.phase='main' AND q.resolved_at IS NULL
                    AND NOT EXISTS (SELECT 1 FROM answer_submissions a WHERE a.session_question_id=q.id) ORDER BY q.position",[$entry['id']]);
                $words=$repo->getWordsByLessonId((int)$entry['lesson_id']);$bank=[];
                foreach($repo->exercises((int)$entry['lesson_id']) as $exercise) {
                    if($exercise['type']==='match_pairs') { continue; }
                    $snapshot=$grader->snapshot($exercise,$words);
                    if($snapshot!==null) { $bank[]=['exercise_id'=>(int)$exercise['id'],'snapshot'=>$snapshot]; }
                }
                if(count($bank)<count($pending)) { return 0; }
                $avoid=[];foreach($repo->questions((int)$entry['id']) as $old) { if($old['phase']==='main' && $old['resolved_at']!==null) { $avoid=array_merge($avoid,json_decode($old['snapshot_json'],true)['target_word_ids']); } }
                $selected=QuestionSelection::main($bank,count($pending),$avoid);
                foreach($pending as $i=>$question) {
                    $item=$selected[$i];foreach(['options','tokens'] as $key) { if(isset($item['snapshot']['content'][$key])) { shuffle($item['snapshot']['content'][$key]); } }
                    $this->db->prepare('UPDATE session_questions SET exercise_id=?,snapshot_json=? WHERE id=?')->execute([$item['exercise_id'],json_encode($item['snapshot'],JSON_THROW_ON_ERROR),$question['id']]);
                }
                $rules['question_bank_version']='mixed-cefr-v4';
                $this->db->prepare('UPDATE learning_sessions SET rules_json=? WHERE id=?')->execute([json_encode($rules,JSON_THROW_ON_ERROR),$entry['id']]);
                return count($pending);
            });
        }
        return $count;
    }
}
