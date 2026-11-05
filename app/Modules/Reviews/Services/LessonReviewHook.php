<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Services;

use App\Modules\Reviews\Repositories\ReviewRepository;
use App\Modules\Reviews\Requests\ReviewException;

/** Server-only bridge; participates in Learning's existing transaction. */
final class LessonReviewHook
{
    public static function afterLessonCompleted(
        \PDO $pdo,
        int $userId,
        array $learnedWordIds,
        \DateTimeImmutable $completedAt,
        array $directions = ['en_vi']
    ): array {
        return (new ReviewStateInitializer($pdo))->withinTransaction(
            $userId, $learnedWordIds, $directions, $completedAt
        );
    }

    /**
     * Uses only an owned, completed lesson and immutable private question snapshots.
     * Never infer previously learned targets from exercises/lesson_words edited later.
     */
    public static function afterSessionCompleted(
        \PDO $pdo,
        int $userId,
        int $sessionId
    ): array {
        if (!$pdo->inTransaction()) {
            throw new \LogicException('Call the review hook inside the lesson transaction.');
        }

        (new ReviewRepository($pdo))->user($userId, true);
        $query = $pdo->prepare(
            "SELECT * FROM learning_sessions
             WHERE id=? AND user_id=? AND mode='lesson' FOR UPDATE"
        );
        $query->execute([$sessionId, $userId]);
        $session = $query->fetch(\PDO::FETCH_ASSOC);
        if (!$session) {
            throw new ReviewException('NOT_FOUND', 'Không tìm thấy lượt học.', 404);
        }
        if ($session['status'] !== 'completed' || $session['finished_at'] === null) {
            throw new ReviewException('LESSON_NOT_COMPLETED', 'Bài học chưa hoàn thành.', 409);
        }

        $rules = ReviewSupport::decode($session['rules_json']);
        if (isset($rules['review_initialization'])) {
            return $rules['review_initialization'];
        }

        $query = $pdo->prepare(
            'SELECT phase,resolved_at,snapshot_json FROM session_questions
             WHERE session_id=? ORDER BY id'
        );
        $query->execute([$sessionId]);
        $questions = $query->fetchAll(\PDO::FETCH_ASSOC);
        $ids = [];
        $mainCount = 0;
        $legacyCount = 0;

        foreach ($questions as $question) {
            if ($question['resolved_at'] === null) {
                throw new ReviewException('LESSON_NOT_COMPLETED', 'Còn câu hỏi chưa hoàn thành.', 409);
            }
            if ($question['phase'] === 'mistake_review') {
                continue;
            }
            if ($question['phase'] === 'main') {
                $mainCount++;
            }
            $snapshot = ReviewSupport::decode($question['snapshot_json']);
            // Sessions started before this bridge had no frozen target IDs.
            // Let them finish, report the limitation, and never guess the missing IDs.
            if (!array_key_exists('target_word_ids', $snapshot)) {
                $legacyCount++;
                continue;
            }
            $targets = $snapshot['target_word_ids'];
            if (!is_array($targets) || !array_is_list($targets)
                || $targets === [] || count($targets) > 500) {
                throw new ReviewException('CORRUPT_SNAPSHOT', 'Danh sách từ trong snapshot không hợp lệ.', 500);
            }
            foreach ($targets as $wordId) {
                if (!is_int($wordId) || $wordId < 1) {
                    throw new ReviewException('CORRUPT_SNAPSHOT', 'ID từ trong snapshot không hợp lệ.', 500);
                }
                $ids[$wordId] = $wordId;
            }
        }
        if ($mainCount < 1 || $mainCount !== (int) $session['total_questions']) {
            throw new ReviewException('CORRUPT_SNAPSHOT', 'Số câu hỏi không khớp lượt học.', 500);
        }

        sort($ids, SORT_NUMERIC);
        $result = [
            'initialized' => 0,
            'already_learned' => 0,
            'skipped' => [],
            'legacy_questions' => $legacyCount,
        ];
        $initializer = new ReviewStateInitializer($pdo);
        $completedAt = new \DateTimeImmutable($session['finished_at'], new \DateTimeZone('UTC'));
        foreach (array_chunk($ids, 500) as $chunk) {
            $part = $initializer->withinTransaction($userId, $chunk, ['en_vi'], $completedAt);
            $result['initialized'] += $part['initialized'];
            $result['already_learned'] += $part['already_learned'];
            $result['skipped'] = array_merge($result['skipped'], $part['skipped']);
        }

        // Persist the outcome with completion/rewards/states in the same transaction.
        $rules['review_initialization'] = $result;
        $pdo->prepare('UPDATE learning_sessions SET rules_json=? WHERE id=? AND user_id=?')
            ->execute([ReviewSupport::json($rules), $sessionId, $userId]);
        return $result;
    }
}
