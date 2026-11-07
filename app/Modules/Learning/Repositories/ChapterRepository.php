<?php
declare(strict_types=1);
namespace App\Modules\Learning\Repositories;

use PDO;
use PDOException;

final class ChapterRepository
{
    public function __construct(private PDO $db) {}
    public function installed(): bool
    {
        try { $this->db->query('SELECT attempt_id FROM user_chapter_passes LIMIT 1'); return true; }
        catch (PDOException $e) { if ($e->getCode() === '42S02') return false; throw $e; }
    }
    public function rows(string $sql, array $args = []): array
    {
        $query = $this->db->prepare($sql); $query->execute($args); return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    public function write(string $sql, array $args): void { $this->db->prepare($sql)->execute($args); }
    public function transaction(callable $work): mixed
    {
        $this->db->beginTransaction();
        try { $result = $work(); $this->db->commit(); return $result; }
        catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    public function lockUser(int $id): array
    {
        return $this->rows('SELECT * FROM users WHERE id=? FOR UPDATE', [$id])[0] ?? [];
    }
    public function states(int $userId): array
    {
        if (!$this->installed()) return [];
        $lessons = $this->rows("SELECT t.id,t.position,c.lesson_id,
            EXISTS(SELECT 1 FROM learning_sessions s WHERE s.user_id=? AND s.lesson_id=c.lesson_id AND s.mode='lesson' AND s.status='completed') done
            FROM topics t JOIN learning_topic_catalog c ON c.topic_id=t.id
            JOIN lessons l ON l.id=c.lesson_id WHERE t.is_active=1 AND l.is_active=1 AND c.word_count>=2
            ORDER BY t.position,t.id,c.level", [$userId]);
        $passes = array_column($this->rows('SELECT topic_id FROM user_chapter_passes WHERE user_id=?', [$userId]), 'topic_id');
        $history = array_column($this->rows("SELECT DISTINCT l.topic_id FROM learning_sessions s JOIN lessons l ON l.id=s.lesson_id WHERE s.user_id=? AND s.mode='lesson'", [$userId]), 'topic_id');
        $groups = [];
        foreach ($lessons as $lesson) $groups[(int)$lesson['id']][] = $lesson;
        $states = []; $previousDone = true;
        foreach ($groups as $topicId => $items) {
            $passed = in_array($topicId, array_map('intval', $passes), true);
            $done = !array_filter($items, static fn ($l) => !(bool)$l['done']);
            $states[$topicId] = ['unlocked' => $previousDone || $passed || in_array($topicId, array_map('intval', $history), true),
                'passed' => $passed, 'completed' => $done, 'available' => true];
            $previousDone = $previousDone && ($passed || $done);
        }
        return $states;
    }
    public function activeAttempt(int $userId, int $topicId): ?array
    {
        return $this->rows("SELECT * FROM chapter_attempts WHERE user_id=? AND topic_id=? AND status='in_progress' ORDER BY id DESC LIMIT 1", [$userId,$topicId])[0] ?? null;
    }
    public function attempt(int $userId, int $id, bool $lock = false): ?array
    {
        return $this->rows('SELECT * FROM chapter_attempts WHERE user_id=? AND id=?' . ($lock ? ' FOR UPDATE' : ''), [$userId,$id])[0] ?? null;
    }
    public function create(int $userId, int $topicId, array $questions): int
    {
        $this->write('INSERT INTO chapter_attempts(user_id,topic_id,questions_json,submissions_json) VALUES(?,?,?,?)',
            [$userId,$topicId,json_encode($questions, JSON_THROW_ON_ERROR),'{}']);
        return (int)$this->db->lastInsertId();
    }
    public function save(array $attempt, array $submissions): void
    {
        $this->write("UPDATE chapter_attempts SET current_index=?,correct_count=?,status=?,submissions_json=?,finished_at=IF(?='in_progress',NULL,UTC_TIMESTAMP()) WHERE id=?",
            [$attempt['current_index'],$attempt['correct_count'],$attempt['status'],json_encode($submissions, JSON_THROW_ON_ERROR),$attempt['status'],$attempt['id']]);
        if ($attempt['status'] === 'completed') $this->write('INSERT INTO user_chapter_passes(user_id,topic_id,attempt_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE attempt_id=VALUES(attempt_id),passed_at=UTC_TIMESTAMP()',
            [$attempt['user_id'],$attempt['topic_id'],$attempt['id']]);
    }
}
