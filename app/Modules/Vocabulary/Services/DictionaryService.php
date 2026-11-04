<?php
declare(strict_types=1);

namespace App\Modules\Vocabulary\Services;

use App\Core\ApiException;
use JsonException;

final class DictionaryService
{
    private const BASE_URL =
        'https://api.dictionaryapi.dev/api/v2/entries/en/';

    private const MAX_BYTES = 1048576; // Tối đa 1 MiB kết quả.

    public function lookup(string $word): array
    {
        $word = strtolower(trim($word));

        if (
            strlen($word) > 80
            || preg_match("/\A[a-z]+(?:[ '-][a-z]+)*\z/", $word) !== 1
        ) {
            throw new ApiException(
                'VALIDATION_FAILED',
                'Nhập từ hoặc cụm từ tiếng Anh, tối đa 80 ký tự.',
                422
            );
        }

        if (!extension_loaded('curl')) {
            throw new ApiException(
                'INTERNAL_ERROR',
                'PHP cần bật extension curl để tra từ.',
                500
            );
        }

        // LIÊN QUAN CƯỜNG:
        // Dùng ApiException chung để Router trả đúng cấu trúc lỗi.
        // URL nguồn cố định, không nhận URL từ người dùng.
        $handle = curl_init(self::BASE_URL . rawurlencode($word));

        if ($handle === false) {
            throw new ApiException(
                'DICTIONARY_UNAVAILABLE',
                'Chưa thể kết nối từ điển.',
                503
            );
        }

        $body = '';
        $tooLarge = false;

        try {
            curl_setopt_array($handle, [
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => 'DoulingoCourseProject/1.0',

                CURLOPT_WRITEFUNCTION => static function (
                    $curl,
                    string $chunk
                ) use (&$body, &$tooLarge): int {
                    if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                        $tooLarge = true;
                        return 0;
                    }

                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);

            $ok = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $error = curl_errno($handle);
        } finally {
            unset($handle);
        }

        if ($tooLarge) {
            throw new ApiException(
                'DICTIONARY_BAD_RESPONSE',
                'Kết quả từ điển vượt giới hạn.',
                502
            );
        }

        if ($ok === false) {
            throw new ApiException(
                'DICTIONARY_UNAVAILABLE',
                'Tra từ thất bại. Vui lòng thử lại sau.',
                $error === CURLE_OPERATION_TIMEDOUT ? 504 : 503
            );
        }

        if ($status === 404) {
            throw new ApiException(
                'WORD_NOT_FOUND',
                'Từ điển chưa có kết quả cho từ này.',
                404
            );
        }

        if ($status === 429) {
            throw new ApiException(
                'DICTIONARY_RATE_LIMITED',
                'Từ điển đang giới hạn lượt tra. Vui lòng thử lại sau.',
                503
            );
        }

        if ($status !== 200) {
            throw new ApiException(
                'DICTIONARY_UNAVAILABLE',
                'Dịch vụ từ điển tạm thời không khả dụng.',
                503
            );
        }

        try {
            $entries = json_decode(
                $body,
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new ApiException(
                'DICTIONARY_BAD_RESPONSE',
                'Từ điển trả dữ liệu không hợp lệ.',
                502
            );
        }

        if (
            !is_array($entries)
            || !array_is_list($entries)
            || $entries === []
        ) {
            throw new ApiException(
                'DICTIONARY_BAD_RESPONSE',
                'Kết quả từ điển sai cấu trúc.',
                502
            );
        }

        foreach ($entries as $entry) {
            if (
                !is_array($entry)
                || !is_string($entry['word'] ?? null)
                || !is_array($entry['meanings'] ?? null)
            ) {
                throw new ApiException(
                    'DICTIONARY_BAD_RESPONSE',
                    'Mục từ thiếu dữ liệu cần thiết.',
                    502
                );
            }
        }

        // LIÊN QUAN DANH:
        // Dùng khi chuẩn bị nội dung, không gọi mỗi lần bấm Học.

        // LIÊN QUAN TUẤN:
        // Chưa tạo word_id hoặc thay đổi lịch SM-2 tại đây.

        // Giữ các nghĩa, nguồn và license nếu API cung cấp.
        // Admin sẽ chọn nghĩa trước khi lưu ở bước sau.
        return [
            'provider' => 'free_dictionary',
            'query' => $word,
            'entries' => $entries,
        ];
    }
}