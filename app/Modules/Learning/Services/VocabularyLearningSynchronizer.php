<?php
declare(strict_types=1);
namespace App\Modules\Learning\Services;
use PDO;
use PDOException;

/** Materialize one shared, versioned question bank without rewriting old snapshots. */
final class VocabularyLearningSynchronizer
{
    public function __construct(private PDO $db) {}

    public function enabled(): bool
    {
        try { $this->db->query('SELECT lesson_id FROM learning_topic_catalog LIMIT 1'); return true; }
        catch(PDOException $error) {
            if($error->getCode()==='42S02' || ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite' && str_contains($error->getMessage(),'no such table'))) { return false; }
            throw $error;
        }
    }
    private function rows(string $sql,array $params=[]): array
    {
        $q=$this->db->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    private function write(string $sql,array $params=[]): void { $this->db->prepare($sql)->execute($params); }

    public function all(): void
    {
        if($this->db->inTransaction() || !$this->enabled()) { return; }
        foreach($this->rows('SELECT id FROM topics WHERE is_active=1 ORDER BY position,id') as $topic) { $this->topic((int)$topic['id']); }
    }
    public function topic(int $topicId): void
    {
        if($this->db->inTransaction() || !$this->enabled()) { return; }
        $this->db->beginTransaction();
        try {
            $topic=$this->rows('SELECT * FROM topics WHERE id=? AND is_active=1 FOR UPDATE',[$topicId])[0]??null;
            if($topic===null) { $this->db->commit();return; }
            $words=$this->rows('SELECT * FROM words WHERE topic_id=? AND is_active=1 ORDER BY id',[$topicId]);
            $cefr=\App\Shared\Cefr::installed($this->db);
            $mappings=array_column($this->rows('SELECT * FROM learning_topic_catalog WHERE topic_id=?',[$topicId]),null,'level');
            $lessons=$this->rows('SELECT * FROM lessons WHERE topic_id=? ORDER BY position,id',[$topicId]);
            $used=array_column($mappings,'lesson_id');$position=0;
            foreach($lessons as $lesson) { $position=max($position,(int)$lesson['position']); }
            foreach(range(1,$cefr?6:5) as $level) {
                $band=\App\Shared\Cefr::LEVELS[$level-1];
                $pool=$cefr?array_values(array_filter($words,static fn($w)=>$w['cefr_level']===$band)):$words;
                $label=$cefr?$band:'Mức '.$level;
                $hash=hash('sha256','mixed-vocabulary-v5-speech:'.json_encode([$topic['name'],$pool,$cefr,$band],JSON_THROW_ON_ERROR));
                $map=$mappings[$level]??null;
                if($map===null) {
                    // Adopt an existing active level to preserve its ID and completion history.
                    $existing=null;
                    foreach($lessons as $lesson) {
                        if((int)$lesson['level']===$level && (int)$lesson['is_active']===1 && !in_array($lesson['id'],$used)) { $existing=$lesson;break; }
                    }
                    if($existing!==null) { $id=(int)$existing['id']; }
                    else {
                        $name=mb_substr($topic['name'],0,130).' · '.$label;
                        $this->write('INSERT INTO lessons(topic_id,name,level,position,question_count,pass_percent,xp_reward,is_active) VALUES(?,?,?,?,10,70,?,1)',[$topicId,$name,$level,++$position,10*$level]);
                        $id=(int)$this->db->lastInsertId();
                    }
                    $used[]=$id;
                    $this->write('INSERT INTO learning_topic_catalog(topic_id,level,lesson_id) VALUES(?,?,?)',[$topicId,$level,$id]);
                    $map=['lesson_id'=>$id,'content_hash'=>''];
                }
                if($map['content_hash']===$hash) { continue; }
                $id=(int)$map['lesson_id'];
                foreach($pool as $word) { $this->write('INSERT IGNORE INTO lesson_words(lesson_id,word_id) VALUES(?,?)',[$id,$word['id']]); }
                // Old rows may be referenced by session_questions: retire rather than delete them.
                $this->write('UPDATE exercises SET is_active=0 WHERE lesson_id=?',[$id]);
                foreach((new VocabularyQuestionFactory())->build($pool,$level) as $row) {
                    $this->write('INSERT INTO exercises(lesson_id,word_id,type,difficulty,content_json,answer_json,is_active) VALUES(?,?,?,?,?,?,1)',
                        [$id,$row['word_id'],$row['type'],$row['difficulty'],json_encode($row['content'],JSON_THROW_ON_ERROR),json_encode($row['answer'],JSON_THROW_ON_ERROR)]);
                }
                $this->write('UPDATE lessons SET name=?,question_count=?,is_active=1 WHERE id=?',
                    [mb_substr($topic['name'],0,130).' · '.$label,max(2,min(10,count($pool))),$id]);
                $this->write('UPDATE learning_topic_catalog SET content_hash=?,word_count=? WHERE topic_id=? AND level=?',[$hash,count($pool),$topicId,$level]);
            }
            $this->db->commit();
        } catch(\Throwable $error) { if($this->db->inTransaction()){$this->db->rollBack();}throw $error; }
    }
}
