<?php
declare(strict_types=1);

namespace App\Modules\Learning\Repositories;

use PDO;
use App\Modules\Learning\Services\VocabularyLearningSynchronizer;

final class LearningRepository
{
    public function __construct(private PDO $db) {}

    public function transaction(callable $work): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $work();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function write(string $sql, array $params): void
    {
        $this->db->prepare($sql)->execute($params);
    }

    public function getActiveTopics(): array
    {
        return $this->rows('SELECT * FROM topics WHERE is_active = 1 ORDER BY position, id');
    }

    public function getActiveLessons(): array
    {
        $sync = new VocabularyLearningSynchronizer($this->db);
        $sync->all();
        if ($sync->enabled()) {
            return $this->rows('SELECT l.*, c.word_count FROM learning_topic_catalog c
                JOIN lessons l ON l.id=c.lesson_id JOIN topics t ON t.id=l.topic_id
                WHERE l.is_active=1 AND t.is_active=1 ORDER BY t.position,t.id,c.level');
        }
        return $this->rows('SELECT l.* FROM lessons l JOIN topics t ON t.id = l.topic_id
            WHERE l.is_active = 1 AND t.is_active = 1 ORDER BY t.position, l.position, l.id');
    }

    public function lesson(int $id): ?array
    {
        $this->syncForLesson($id);
        return $this->rows('SELECT l.* FROM lessons l JOIN topics t ON t.id = l.topic_id
            WHERE l.id = ? AND l.is_active = 1 AND t.is_active = 1', [$id])[0] ?? null;
    }

    public function getWordsByLessonId(int $lessonId): array
    {
        $sameTopic = $this->managedLesson($lessonId) ? ' AND w.topic_id=l.topic_id' : '';
        if ($sameTopic !== '' && \App\Shared\Cefr::installed($this->db)) {
            $sameTopic .= " AND w.cefr_level=CASE l.level WHEN 1 THEN 'A1' WHEN 2 THEN 'A2' WHEN 3 THEN 'B1' WHEN 4 THEN 'B2' WHEN 5 THEN 'C1' WHEN 6 THEN 'C2' END";
        }
        return \App\Modules\Learning\Services\WordArtwork::decorate($this->rows('SELECT w.* FROM words w
            JOIN lesson_words lw ON lw.word_id = w.id JOIN lessons l ON l.id = lw.lesson_id
            JOIN topics t ON t.id = l.topic_id JOIN topics wt ON wt.id = w.topic_id
            WHERE lw.lesson_id = ? AND w.is_active = 1 AND l.is_active = 1
            AND t.is_active = 1 AND wt.is_active = 1' . $sameTopic . ' ORDER BY w.id', [$lessonId]));
    }

    public function getWordsByTopicId(int $topicId, ?string $cefr = null): array
    {
        $filter=$cefr!==null && \App\Shared\Cefr::installed($this->db);
        return \App\Modules\Learning\Services\WordArtwork::decorate($this->rows('SELECT w.* FROM words w JOIN topics t ON t.id=w.topic_id
            WHERE w.topic_id=? AND w.is_active=1 AND t.is_active=1'.($filter?' AND w.cefr_level=?':'').' ORDER BY w.id', $filter?[$topicId,$cefr]:[$topicId]));
    }

    public function syncForLesson(int $id): void
    {
        if ($this->db->inTransaction()) { return; }
        $topic = $this->rows('SELECT topic_id FROM lessons WHERE id=?', [$id])[0] ?? null;
        if ($topic !== null) {
            (new VocabularyLearningSynchronizer($this->db))->topic((int) $topic['topic_id']);
        }
    }

    private function managedLesson(int $id): bool
    {
        return (new VocabularyLearningSynchronizer($this->db))->enabled()
            && $this->rows('SELECT lesson_id FROM learning_topic_catalog WHERE lesson_id=?', [$id]) !== [];
    }

    public function exercises(int $lessonId): array
    {
        return $this->rows('SELECT e.* FROM exercises e JOIN lessons l ON l.id = e.lesson_id
            JOIN topics t ON t.id = l.topic_id LEFT JOIN words w ON w.id = e.word_id
            LEFT JOIN topics wt ON wt.id = w.topic_id
            WHERE e.lesson_id = ? AND e.is_active = 1 AND l.is_active = 1 AND t.is_active = 1
            AND (e.word_id IS NULL OR (w.is_active = 1 AND wt.is_active = 1)) ORDER BY e.id', [$lessonId]);
    }

    public function chapterStates(int $userId): ?array
    {
        $chapters=new ChapterRepository($this->db);
        return $chapters->installed()?$chapters->states($userId):null;
    }

    public function completedLessons(int $userId): array
    {
        return array_map('intval', array_column($this->rows("SELECT DISTINCT lesson_id FROM learning_sessions
            WHERE user_id = ? AND mode = 'lesson' AND status = 'completed'", [$userId]), 'lesson_id'));
    }

    public function unlocked(int $userId, array $lesson): bool
    {
        $chapterRepo=new ChapterRepository($this->db);
        if ($chapterRepo->installed()) {
            $chapter=$chapterRepo->states($userId)[(int)$lesson['topic_id']]??null;
            if ($chapter!==null && !$chapter['unlocked']) return false;
            if ($chapter!==null && $chapter['passed']) return true;
        }
        if ($this->managedLesson((int) $lesson['id'])) {
            return $this->rows("SELECT c.lesson_id FROM learning_topic_catalog c
                JOIN lessons l ON l.id=c.lesson_id JOIN topics t ON t.id=c.topic_id
                WHERE c.topic_id=? AND c.level<? AND c.word_count>=2 AND l.is_active=1 AND t.is_active=1
                AND NOT EXISTS (SELECT 1 FROM learning_sessions s WHERE s.user_id=?
                    AND s.lesson_id=l.id AND s.mode='lesson' AND s.status='completed') LIMIT 1",
                [$lesson['topic_id'], $lesson['level'], $userId]) === [];
        }
        return $this->rows("SELECT l.id FROM lessons l JOIN topics t ON t.id = l.topic_id
            WHERE l.topic_id = ? AND l.position < ? AND l.is_active = 1 AND t.is_active = 1
            AND NOT EXISTS (SELECT 1 FROM learning_sessions s WHERE s.user_id = ?
                AND s.lesson_id = l.id AND s.mode = 'lesson' AND s.status = 'completed') LIMIT 1",
            [$lesson['topic_id'], $lesson['position'], $userId]) === [];
    }


    public function activeSessions(int $userId): array
    {
        $order=\App\Shared\Cefr::installed($this->db)?'COALESCE(s.last_seen_at,s.started_at) DESC,s.id DESC':'s.id DESC';
        return $this->rows("SELECT s.*,(SELECT COUNT(*) FROM session_questions q WHERE q.session_id=s.id AND q.phase='main' AND q.resolved_at IS NOT NULL) AS answered_main
            FROM learning_sessions s JOIN lessons l ON l.id=s.lesson_id JOIN topics t ON t.id=l.topic_id
            WHERE s.user_id=? AND s.mode='lesson' AND s.status='in_progress' AND l.is_active=1 AND t.is_active=1 ORDER BY ".$order,[$userId]);
    }
    public function history(int $userId): array
    {
        return $this->rows("SELECT s.id,s.lesson_id,s.status,s.total_questions,s.correct_count,s.started_at,s.finished_at,l.name,
            (SELECT COUNT(*) FROM session_questions q WHERE q.session_id=s.id AND q.phase='main' AND q.resolved_at IS NOT NULL) AS answered_main
            FROM learning_sessions s JOIN lessons l ON l.id=s.lesson_id WHERE s.user_id=? AND s.mode='lesson' ORDER BY s.id DESC LIMIT 20",[$userId]);
    }
    public function touchSession(int $id): void
    {
        if(\App\Shared\Cefr::installed($this->db)) { $this->write('UPDATE learning_sessions SET last_seen_at=UTC_TIMESTAMP() WHERE id=?',[$id]); }
    }
    public function recordMistakes(int $userId,int $questionId,array $targets): void
    {
        if(!\App\Shared\Cefr::installed($this->db)) { return; }
        foreach(array_unique($targets) as $word) {
            if($this->rows('SELECT word_id FROM learning_mistakes WHERE user_id=? AND word_id=?',[$userId,$word])) {
                $this->write('UPDATE learning_mistakes SET last_question_id=?,wrong_count=wrong_count+1,last_wrong_at=UTC_TIMESTAMP(),resolved_at=NULL WHERE user_id=? AND word_id=?',[$questionId,$userId,$word]);
            } else { $this->write('INSERT INTO learning_mistakes(user_id,word_id,last_question_id) VALUES(?,?,?)',[$userId,$word,$questionId]); }
        }
    }

    public function lockUser(int $id): array
    {
        return $this->rows('SELECT * FROM users WHERE id = ? FOR UPDATE', [$id])[0];
    }

    public function saveHearts(int $id, array $state): void
    {
        $this->write('UPDATE users SET current_hp = ?, next_hp_at = ? WHERE id = ?',
            [$state['current_hp'], $state['next_hp_at'], $id]);
    }

    public function activeSession(int $userId, int $lessonId): ?array
    {
        return $this->rows("SELECT * FROM learning_sessions WHERE user_id = ? AND lesson_id = ?
            AND mode = 'lesson' AND status = 'in_progress' ORDER BY id DESC LIMIT 1", [$userId, $lessonId])[0] ?? null;
    }

    public function session(int $userId, int $id, bool $lock = false, string $mode = 'lesson'): ?array
    {
        return $this->rows("SELECT * FROM learning_sessions WHERE id = ? AND user_id = ? AND mode = ?"
            . ($lock ? ' FOR UPDATE' : ''), [$id, $userId, $mode])[0] ?? null;
    }

    public function createSession(int $userId, ?int $lessonId, int $total, array $rules, string $mode = 'lesson'): int
    {
        $this->write("INSERT INTO learning_sessions (user_id,lesson_id,mode,total_questions,rules_json)
            VALUES (?,?,?,?,?)", [$userId, $lessonId, $mode, $total, json_encode($rules, JSON_THROW_ON_ERROR)]);
        return (int) $this->db->lastInsertId();
    }

    public function addQuestion(int $sessionId, int $exerciseId, string $phase, int $position, string $snapshot, ?int $source = null): void
    {
        $this->write('INSERT INTO session_questions (session_id,exercise_id,phase,position,snapshot_json,source_question_id)
            VALUES (?,?,?,?,?,?)', [$sessionId, $exerciseId, $phase, $position, $snapshot, $source]);
    }

    public function questions(int $sessionId): array
    {
        return $this->rows("SELECT * FROM session_questions WHERE session_id = ?
            ORDER BY FIELD(phase,'main','mistake_review','matching'), position", [$sessionId]);
    }

    public function submission(string $key): ?array
    {
        return $this->rows('SELECT a.*, q.session_id FROM answer_submissions a
            JOIN session_questions q ON q.id = a.session_question_id WHERE a.request_key = ?', [$key])[0] ?? null;
    }

    public function recordAnswer(int $questionId, string $key, array $answer, bool $correct): void
    {
        // The owning user/session lock serializes attempts across tabs and devices.
        $attempt = 1 + (int) $this->rows('SELECT COUNT(*) AS n FROM answer_submissions WHERE session_question_id = ?', [$questionId])[0]['n'];
        $this->write('INSERT INTO answer_submissions (session_question_id,request_key,attempt_no,answer_json,is_correct)
            VALUES (?,?,?,?,?)', [$questionId, $key, $attempt, json_encode($answer, JSON_THROW_ON_ERROR), (int) $correct]);
    }

    public function resolveQuestion(int $id): void
    {
        $this->write('UPDATE session_questions SET resolved_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    public function addCorrect(int $id): void
    {
        $this->write('UPDATE learning_sessions SET correct_count = correct_count + 1 WHERE id = ?', [$id]);
    }

    public function wrongMainQuestions(int $id): array
    {
        return $this->rows("SELECT q.* FROM session_questions q JOIN answer_submissions a ON a.session_question_id = q.id
            WHERE q.session_id = ? AND q.phase = 'main' AND a.attempt_no = 1 AND a.is_correct = 0 ORDER BY q.position", [$id]);
    }

    public function finish(int $id, string $status): void
    {
        $this->write("UPDATE learning_sessions SET status = ?, finished_at = UTC_TIMESTAMP()
            WHERE id = ? AND status = 'in_progress'", [$status, $id]);
    }

    /** Called only after finish/reward, before transaction() commits. */
    public function initializeReviews(int $userId, int $sessionId): array
    {
        return \App\Modules\Reviews\Services\LessonReviewHook::afterSessionCompleted(
            $this->db, $userId, $sessionId
        );
    }

    public function reward(array $user, array $session, int $xp): void
    {
        if ($xp > 0) {
            $this->write("INSERT INTO xp_transactions (user_id,session_id,amount,reason,event_key)
                VALUES (?,?,?,'lesson_completed',?)", [$user['id'], $session['id'], $xp, 'lesson:' . $session['id'] . ':complete']);
        }
        $date = (new \DateTimeImmutable('now', new \DateTimeZone($user['timezone'])))->format('Y-m-d');
        // Only advance already assigned quests; claiming their rewards belongs to Notifications/Quests.
        $this->write("UPDATE user_quests uq JOIN quest_definitions q ON q.id = uq.quest_id
            SET uq.progress = LEAST(q.target, uq.progress + CASE q.metric WHEN 'xp_earned' THEN ? ELSE 1 END)
            WHERE uq.user_id = ? AND uq.quest_date = ? AND uq.reward_claimed_at IS NULL
            AND q.metric IN ('xp_earned','lessons_completed')", [$xp, $user['id'], $date]);
        $this->write('INSERT INTO user_daily_activity (user_id,activity_date,xp_total,completed_lessons,streak_qualified)
            VALUES (?,?,?,1,?) ON DUPLICATE KEY UPDATE xp_total = xp_total + VALUES(xp_total),
            completed_lessons = completed_lessons + 1, streak_qualified = (xp_total >= ?)',
            [$user['id'], $date, $xp, (int) ($xp >= (int) $user['daily_goal_xp']), $user['daily_goal_xp']]);
    }

    public function stats(int $userId): array
    {
        $xp = (int) $this->rows('SELECT COALESCE(SUM(amount),0) AS n FROM xp_transactions WHERE user_id = ?', [$userId])[0]['n'];
        $days = array_column($this->rows('SELECT activity_date FROM user_daily_activity
            WHERE user_id = ? AND streak_qualified = 1 ORDER BY activity_date DESC', [$userId]), 'activity_date');
        return ['xp' => $xp, 'qualified_dates' => $days];
    }
}
