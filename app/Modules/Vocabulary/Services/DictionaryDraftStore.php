<?php
declare(strict_types=1);

namespace App\Modules\Vocabulary\Services;

use App\Core\ApiException;
use App\Core\Session;

final class DictionaryDraftStore
{
    public function remember(array $data): string
    {
        Session::start();
        $token = bin2hex(random_bytes(32));
        // Một lượt tra hiện hành mỗi phiên; tra lại thì bản nháp cũ hết hiệu lực.
        $_SESSION['vocabulary_draft'] = [
            'token' => $token,
            'expires' => time() + 1800,
            'data' => $data,
        ];
        return $token;
    }

    public function find(string $token, string $key): array
    {
        Session::start();
        $draft = $_SESSION['vocabulary_draft'] ?? null;
        if (!is_array($draft)
            || !is_string($draft['token'] ?? null)
            || !is_int($draft['expires'] ?? null)
            || $draft['expires'] <= time()
            || !hash_equals($draft['token'], $token)) {
            throw new ApiException('DRAFT_EXPIRED', 'Lượt tra hết hạn hoặc đã thay đổi. Hãy tra từ lại.', 422);
        }
        foreach (($draft['data']['candidates'] ?? []) as $candidate) {
            if (($candidate['candidate_key'] ?? null) === $key) {
                return ['provider' => $draft['data']['provider'], 'candidate' => $candidate];
            }
        }
        throw new ApiException('INVALID_CANDIDATE', 'Nghĩa đã chọn không thuộc lượt tra này.', 422);
    }
}
