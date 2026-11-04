<?php
declare(strict_types=1);

namespace App\Modules\Vocabulary\Services;

use App\Core\ApiException;

final class DictionaryNormalizer
{
    public function normalize(array $lookup): array
    {
        $candidates = [];

        foreach ($this->rows($lookup['entries'] ?? null) as $entryIndex => $entry) {
            if (
                !is_array($entry)
                || $this->text($entry['word'] ?? null) === null
            ) {
                continue;
            }

            $phonetics = [];

            foreach ($this->rows($entry['phonetics'] ?? null) as $phonetic) {
                if (!is_array($phonetic)) {
                    continue;
                }

                $ipa = $this->text($phonetic['text'] ?? null);
                $audio = $this->httpsUrl($phonetic['audio'] ?? null);

                if ($ipa !== null || $audio !== null) {
                    // Giữ IPA/audio cùng mục phát âm.
                    // Không tự ghép IPA giọng Anh với audio giọng Mỹ.
                    $phonetics[] = [
                        'ipa' => $ipa,
                        'audio' => $audio,
                        'source_url' => $this->httpsUrl(
                            $phonetic['sourceUrl'] ?? null
                        ),
                        'license' => $phonetic['license'] ?? null,
                    ];
                }
            }

            if (
                $phonetics === []
                && $this->text($entry['phonetic'] ?? null) !== null
            ) {
                $phonetics[] = [
                    'ipa' => trim($entry['phonetic']),
                    'audio' => null,
                    'source_url' => null,
                    'license' => null,
                ];
            }

            foreach ($this->rows($entry['meanings'] ?? null) as $meaningIndex => $meaning) {
                if (!is_array($meaning)) {
                    continue;
                }

                $sourcePos = $this->text($meaning['partOfSpeech'] ?? null);

                // API có thể dùng exclamation; project dùng interjection.
                $pos = $sourcePos === 'exclamation'
                    ? 'interjection'
                    : $sourcePos;

                $allowed = [
                    'noun', 'verb', 'adjective', 'adverb', 'pronoun',
                    'preposition', 'conjunction', 'interjection',
                    'determiner', 'phrase',
                ];

                foreach ($this->rows($meaning['definitions'] ?? null) as $definitionIndex => $definition) {
                    if (!is_array($definition)) {
                        continue;
                    }

                    $text = $this->text($definition['definition'] ?? null);

                    if ($text === null) {
                        continue;
                    }

                    $candidates[] = [
                        // Khóa tạm trong lượt tra cứu, không phải words.id.
                        'candidate_key' =>
                            "$entryIndex:$meaningIndex:$definitionIndex",

                        'english' => trim($entry['word']),

                        'part_of_speech' =>
                            in_array($pos, $allowed, true) ? $pos : null,

                        'source_part_of_speech' => $sourcePos,
                        'definition_en' => $text,

                        'example' => $this->text(
                            $definition['example'] ?? null
                        ),

                        'phonetics' => $phonetics,

                        'source_urls' => array_values(array_filter(
                            array_map(
                                fn ($url) => $this->httpsUrl($url),
                                $this->rows($entry['sourceUrls'] ?? null)
                            )
                        )),

                        'license' => $entry['license'] ?? null,
                    ];
                }
            }
        }

        if ($candidates === []) {
            throw new ApiException(
                'DICTIONARY_BAD_RESPONSE',
                'Không có nghĩa hợp lệ để hiển thị.',
                502
            );
        }

        // LIÊN QUAN CƯỜNG:
        // Controller sẽ bọc kết quả bằng Response::data().

        // LIÊN QUAN DANH:
        // Đây là dữ liệu cho Admin duyệt, chưa phải nội dung bài học.

        // LIÊN QUAN TUẤN:
        // Chỉ words.id sau khi lưu mới dùng cho trạng thái SM-2.
        return [
            'provider' => $lookup['provider'] ?? 'free_dictionary',
            'query' => $lookup['query'] ?? '',
            'candidates' => $candidates,
        ];
    }

    private function rows(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : null;
    }

    private function httpsUrl(mixed $value): ?string
    {
        $url = $this->text($value);

        if ($url === null) {
            return null;
        }

        // Một số URL API trả về bắt đầu bằng //.
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
                ? $url
                : null;
    }
}