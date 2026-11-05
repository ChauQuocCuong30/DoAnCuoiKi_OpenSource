<?php
declare(strict_types=1);

namespace App\Modules\Learning\Repositories;

use App\Core\ApiException;
use PDO;

final class GuestClaimRepository
{
    public function __construct(private PDO $db) {}

    public function reserve(string $token, int $userId, int $lessonId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO learning_guest_claims (token,user_id,lesson_id)
             VALUES (?,?,?) ON DUPLICATE KEY UPDATE token = token'
        );
        $stmt->execute([$token, $userId, $lessonId]);

        $stmt = $this->db->prepare(
            'SELECT user_id,lesson_id
             FROM learning_guest_claims WHERE token = ? FOR UPDATE'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row
            || (int) $row['user_id'] !== $userId
            || (int) $row['lesson_id'] !== $lessonId
        ) {
            throw new ApiException(
                'GUEST_ALREADY_CLAIMED',
                'Kết quả này đã được gắn vào tài khoản khác.',
                409
            );
        }
    }

    public function attach(
        string $token,
        int $userId,
        int $sessionId
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE learning_guest_claims SET session_id = ?
             WHERE token = ? AND user_id = ?'
        );
        $stmt->execute([$sessionId, $token, $userId]);
    }
}