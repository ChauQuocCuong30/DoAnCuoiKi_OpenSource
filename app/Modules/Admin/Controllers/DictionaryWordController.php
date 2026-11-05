<?php
declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\ApiException;
use App\Core\Response;
use App\Middleware\Auth;
use App\Modules\Vocabulary\Repositories\WordRepository;
use App\Modules\Vocabulary\Services\DictionaryDraftStore;
use JsonException;

final class DictionaryWordController
{
    public function store(array $params): void
    {
        Auth::admin();
        header('Cache-Control: no-store');
        $raw = file_get_contents('php://input', false, null, 0, 32769);
        if ($raw === false || strlen($raw) > 32768) {
            throw new ApiException('INVALID_BODY', 'Dữ liệu gửi quá lớn.', 413);
        }
        try {
            $body = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException('INVALID_JSON', 'JSON không hợp lệ.', 400);
        }
        if (!is_array($body) || array_is_list($body)) {
            throw new ApiException('INVALID_BODY', 'Dữ liệu phải là JSON object.', 422);
        }
        // Token ngẫu nhiên nằm trong phiên: trình duyệt phải đọc được lượt tra
        // cùng nguồn mới có thể lưu; không nhận nghĩa/API metadata tùy ý từ client.
        $draft = (new DictionaryDraftStore())->find(
            $this->field($body, 'lookup_token', 64),
            $this->field($body, 'candidate_key', 100)
        );
        $candidate = $draft['candidate'];
        $topic = $this->field($body, 'topic_id', 18);
        if (!preg_match('/^[1-9][0-9]*$/D', $topic)) {
            throw new ApiException('VALIDATION_FAILED', 'Chủ đề không hợp lệ.', 422);
        }
        $pos = $this->field($body, 'part_of_speech', 30);
        if (!in_array($pos, ['noun', 'verb', 'adjective', 'adverb', 'pronoun',
            'preposition', 'conjunction', 'interjection', 'determiner', 'phrase'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Từ loại không hợp lệ.', 422);
        }
        $phoneticIndex = $this->field($body, 'phonetic_index', 5, false);
        $phonetic = null;
        if ($phoneticIndex !== '') {
            if (!ctype_digit($phoneticIndex)
                || !isset($candidate['phonetics'][(int) $phoneticIndex])) {
                throw new ApiException('VALIDATION_FAILED', 'Phát âm không hợp lệ.', 422);
            }
            $phonetic = $candidate['phonetics'][(int) $phoneticIndex];
        }
        $word = [
            'topic_id' => (int) $topic,
            'english' => $this->field($candidate, 'english', 150),
            'vietnamese' => $this->field($body, 'vietnamese', 255),
            'part_of_speech' => $pos,
            'ipa' => $this->field($phonetic ?? [], 'ipa', 150, false) ?: null,
            'audio' => $this->url($this->field($phonetic ?? [], 'audio', 255, false)),
            'example' => $this->field($body, 'example', 5000),
            'example_translation' => $this->field($body, 'example_translation', 5000, false) ?: null,
            'image' => $this->url($this->field($body, 'image', 255, false)),
        ];
        $imageSource = $this->url($this->field($body, 'image_source', 2000, false));
        $imageLicense = $this->field($body, 'image_license', 255, false);
        if ($word['image'] !== null && ($imageSource === null || $imageLicense === '')) {
            throw new ApiException('VALIDATION_FAILED', 'Ảnh cần nguồn và giấy phép/quyền sử dụng.', 422);
        }
        $id = (new WordRepository())->create($word, [
            'provider' => $draft['provider'], 'candidate' => $candidate,
            'selected_phonetic' => $phonetic, 'saved_at' => gmdate('c'),
            'image_source' => $imageSource, 'image_license' => $imageLicense,
        ]);
        Response::data(['id' => $id, 'english' => $word['english']], 201);
    }

    private function field(array $data, string $key, int $max, bool $required = true): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value) || mb_strlen(trim($value), 'UTF-8') > $max
            || ($required && trim($value) === '')) {
            throw new ApiException('VALIDATION_FAILED', 'Trường ' . $key . ' thiếu hoặc không hợp lệ.', 422);
        }
        return trim($value);
    }

    private function url(string $url): ?string
    {
        if ($url === '') return null;
        if (!filter_var($url, FILTER_VALIDATE_URL)
            || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || parse_url($url, PHP_URL_USER) !== null
            || parse_url($url, PHP_URL_PASS) !== null) {
            throw new ApiException('VALIDATION_FAILED', 'URL phải dùng HTTPS, không chứa thông tin đăng nhập.', 422);
        }
        // Chỉ lưu URL, backend không tải nội dung từ URL này.
        return $url;
    }
}
