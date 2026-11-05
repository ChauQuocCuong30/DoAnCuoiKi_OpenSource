<?php
declare(strict_types=1);
namespace App\Modules\Admin\Requests;
use App\Core\ApiException;

// LIÊN QUAN DANH/TUẤN: schema câu hỏi v1 theo dữ liệu mẫu; đáp án tách riêng khỏi đề.
final class ContentInput
{
    public const POS = [
        'noun',
        'verb',
        'adjective',
        'adverb',
        'pronoun',
        'preposition',
        'conjunction',
        'interjection',
        'determiner',
        'phrase',
    ];
    public const TYPES = [
        'choose_meaning',
        'choose_image',
        'fill_blank',
        'token_order',
        'listen_select',
        'listen_type',
        'match_pairs',
        'translate_text',
        'sound_discrimination',
    ];
    public static function fail(string $message): never
    {
        throw new ApiException('VALIDATION_FAILED', $message, 422);
    }
    public static function text(
        array $data,
        string $key,
        int $max,
        bool $required = true,
    ): string {
        $v = $data[$key] ?? '';
        if (
            !is_string($v) ||
            !mb_check_encoding($v, 'UTF-8') ||
            mb_strlen(trim($v), 'UTF-8') > $max ||
            ($required && trim($v) === '')
        ) {
            self::fail('Trường ' . $key . ' thiếu hoặc sai định dạng.');
        }
        return trim($v);
    }
    public static function number(
        mixed $v,
        int $min = 1,
        int $max = 2147483647,
    ): int {
        if (
            (!is_string($v) && !is_int($v)) ||
            !preg_match('/^(0|[1-9][0-9]{0,17})$/D', (string) $v) ||
            (int) $v < $min ||
            (int) $v > $max
        ) {
            self::fail(
                'Giá trị số phải trong khoảng ' . $min . '–' . $max . '.',
            );
        }
        return (int) $v;
    }
    public static function id(mixed $v): int
    {
        return self::number($v, 1, PHP_INT_MAX);
    }
    public static function url(
        array $data,
        string $key,
        int $max = 255,
    ): ?string {
        $v = self::text($data, $key, $max, false);
        if ($v === '') {
            return null;
        }
        if (
            !filter_var($v, FILTER_VALIDATE_URL) ||
            parse_url($v, PHP_URL_SCHEME) !== 'https' ||
            parse_url($v, PHP_URL_USER) !== null ||
            parse_url($v, PHP_URL_PASS) !== null
        ) {
            self::fail($key . ' phải là URL HTTPS, không chứa tài khoản.');
        }
        return $v;
    }
    public static function ids(mixed $v, int $max = 200): array
    {
        if (is_string($v)) {
            $v = trim($v) === '' ? [] : preg_split('/\s*,\s*/', trim($v));
        }
        if (!is_array($v) || !array_is_list($v) || count($v) > $max) {
            self::fail('Danh sách ID không hợp lệ, tối đa ' . $max . '.');
        }
        $ids = array_map(static fn($id) => self::id($id), $v);
        if (count(array_unique($ids)) !== count($ids)) {
            self::fail('Danh sách ID có phần tử trùng.');
        }
        sort($ids, SORT_NUMERIC);
        return $ids;
    }
    public static function word(array $b): array
    {
        $pos = self::text($b, 'part_of_speech', 30);
        if (!in_array($pos, self::POS, true)) {
            self::fail('Từ loại không hợp lệ.');
        }
        $english = self::text($b, 'english', 150);
        if (!preg_match("/^[A-Za-z][A-Za-z '\\-]*$/D", $english)) {
            self::fail(
                'Từ tiếng Anh chỉ gồm chữ cái, dấu cách, gạch nối và dấu nháy.',
            );
        }
        $word = [
            'topic_id' => self::id($b['topic_id'] ?? null),
            'english' => mb_strtolower($english, 'UTF-8'),
            'vietnamese' => self::text($b, 'vietnamese', 255),
            'part_of_speech' => $pos,
            'ipa' => self::text($b, 'ipa', 150, false) ?: null,
            'example' => self::text($b, 'example', 5000),
            'example_translation' =>
                self::text($b, 'example_translation', 5000, false) ?: null,
            'image' => self::url($b, 'image'),
            'audio' => self::url($b, 'audio'),
            'is_active' => self::number($b['is_active'] ?? 1, 0, 1),
        ];
        $source = [
            'provider' => 'admin_manual',
            'saved_at' => gmdate('c'),
            'manual' => [],
        ];
        foreach (['image', 'audio'] as $media) {
            $origin = self::url($b, $media . '_source', 2000);
            $license = self::text($b, $media . '_license', 255, false);
            if (
                $word[$media] !== null &&
                ($origin === null || $license === '')
            ) {
                self::fail($media . ' cần nguồn và giấy phép/quyền sử dụng.');
            }
            $source['manual'][$media . '_source'] = $origin;
            $source['manual'][$media . '_license'] = $license;
        }
        return ['word' => $word, 'source' => $source];
    }
    public static function lesson(array $b): array
    {
        $pass = $b['pass_percent'] ?? '';
        if (
            (!is_int($pass) && !is_float($pass) && !is_string($pass)) ||
            !preg_match('/^\d{1,2}(\.\d{1,2})?$/D', (string) $pass) ||
            (float) $pass >= 100
        ) {
            self::fail(
                'Tỷ lệ đạt phải từ 0 đến dưới 100, tối đa 2 chữ số thập phân.',
            );
        }
        return [
            'topic_id' => self::id($b['topic_id'] ?? null),
            'name' => self::text($b, 'name', 150),
            'level' => self::number($b['level'] ?? 1, 1, 5),
            'position' => self::number($b['position'] ?? null),
            'question_count' => self::number(
                $b['question_count'] ?? 10,
                1,
                100,
            ),
            'pass_percent' => (float) $pass,
            'xp_reward' => self::number($b['xp_reward'] ?? 10, 0, 10000),
            'is_active' => self::number($b['is_active'] ?? 0, 0, 1),
            'word_ids' => self::ids($b['word_ids'] ?? []),
        ];
    }
    public static function object(mixed $v, string $field): array
    {
        if (is_string($v)) {
            try {
                $obj = json_decode($v, false, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                self::fail($field . ' không phải JSON hợp lệ.');
            }
            if (!($obj instanceof \stdClass)) {
                self::fail($field . ' phải là JSON object.');
            }
            $v = json_decode($v, true, 32, JSON_THROW_ON_ERROR);
        }
        if (!is_array($v) || array_is_list($v)) {
            self::fail($field . ' phải là object.');
        }
        return $v;
    }
    private static function keys(array $v, array $allowed): void
    {
        if (array_diff(array_keys($v), $allowed)) {
            self::fail(
                'JSON có trường không được hỗ trợ: ' .
                    implode(', ', array_diff(array_keys($v), $allowed)),
            );
        }
    }
    private static function strings(
        mixed $v,
        int $min = 1,
        int $max = 20,
    ): array {
        if (
            !is_array($v) ||
            !array_is_list($v) ||
            count($v) < $min ||
            count($v) > $max
        ) {
            self::fail('Danh sách chuỗi không hợp lệ.');
        }
        foreach ($v as &$item) {
            $item = self::text(['v' => $item], 'v', 500);
        }
        unset($item);
        if (count(array_unique($v)) !== count($v)) {
            self::fail('Danh sách có giá trị trùng.');
        }
        return $v;
    }
    private static function options(mixed $v, string $kind): array
    {
        if (
            !is_array($v) ||
            !array_is_list($v) ||
            count($v) < 2 ||
            count($v) > 30
        ) {
            self::fail('Danh sách lựa chọn phải có 2–30 mục.');
        }
        $out = [];
        $ids = [];
        foreach ($v as $item) {
            if (!is_array($item)) {
                self::fail('Lựa chọn không hợp lệ.');
            }
            self::keys($item, ['id', 'text', 'image', 'audio']);
            $id = self::text($item, 'id', 50);
            if (
                !preg_match('/^[a-zA-Z0-9_-]+$/D', $id) ||
                in_array($id, $ids, true)
            ) {
                self::fail('ID lựa chọn sai hoặc trùng.');
            }
            $ids[] = $id;
            $row = [
                'id' => $id,
                'text' => self::text(
                    $item,
                    'text',
                    1000,
                    $kind !== 'choose_image',
                ),
            ];
            foreach (['image', 'audio'] as $key) {
                if (isset($item[$key])) {
                    $row[$key] = self::url($item, $key);
                }
            }
            if ($kind === 'choose_image' && empty($row['image'])) {
                self::fail('Chọn hình cần ảnh trong mọi lựa chọn.');
            }
            $out[] = $row;
        }
        return $out;
    }
    public static function exercise(array $b): array
    {
        $type = self::text($b, 'type', 30);
        if (!in_array($type, self::TYPES, true)) {
            self::fail('Dạng câu hỏi không hợp lệ.');
        }
        $c = self::object($b['content_json'] ?? null, 'content_json');
        $a = self::object($b['answer_json'] ?? null, 'answer_json');
        // LIÊN QUAN DANH/TUẤN: schema_version 1 theo dữ liệu mẫu; đáp án tách riêng.
        $allowed = [
            'schema_version',
            'instruction',
            'prompt',
            'audio',
            'image',
            'target_word_ids',
        ];
        if (
            in_array(
                $type,
                [
                    'choose_meaning',
                    'choose_image',
                    'listen_select',
                    'sound_discrimination',
                ],
                true,
            )
        ) {
            $allowed[] = 'options';
        }
        if ($type === 'token_order') {
            $allowed[] = 'tokens';
        }
        if ($type === 'match_pairs') {
            $allowed = array_merge($allowed, ['left', 'right']);
        }
        self::keys($c, $allowed);
        if (($c['schema_version'] ?? 1) !== 1) {
            self::fail('Chỉ hỗ trợ schema_version 1.');
        }
        $c['schema_version'] = 1;
        $c['instruction'] = self::text($c, 'instruction', 500);
        $c['prompt'] = self::text(
            $c,
            'prompt',
            2000,
            !in_array(
                $type,
                [
                    'token_order',
                    'match_pairs',
                    'listen_select',
                    'listen_type',
                    'sound_discrimination',
                ],
                true,
            ),
        );
        $c['target_word_ids'] = self::ids($c['target_word_ids'] ?? []);
        if ($c['target_word_ids'] === []) {
            self::fail('Câu hỏi cần ít nhất một target_word_id.');
        }
        foreach (['image', 'audio'] as $key) {
            if (isset($c[$key])) {
                $c[$key] = self::url($c, $key);
            }
        }
        if (
            in_array(
                $type,
                ['listen_select', 'listen_type', 'sound_discrimination'],
                true,
            ) &&
            empty($c['audio'])
        ) {
            self::fail('Dạng nghe cần URL audio HTTPS.');
        }
        if (
            in_array(
                $type,
                [
                    'choose_meaning',
                    'choose_image',
                    'listen_select',
                    'sound_discrimination',
                ],
                true,
            )
        ) {
            $c['options'] = self::options($c['options'] ?? null, $type);
            self::keys($a, ['correct_option_ids']);
            $correct = self::strings($a['correct_option_ids'] ?? null, 1, 1);
            if (array_diff($correct, array_column($c['options'], 'id'))) {
                self::fail('Đáp án không thuộc lựa chọn.');
            }
            $a = ['correct_option_ids' => $correct];
        } elseif (
            in_array(
                $type,
                ['fill_blank', 'listen_type', 'translate_text'],
                true,
            )
        ) {
            self::keys($a, ['accepted_answers', 'normalization']);
            $answers = self::strings($a['accepted_answers'] ?? null);
            $normal = $a['normalization'] ?? [
                'trim' => true,
                'case_sensitive' => false,
            ];
            if (!is_array($normal)) {
                self::fail('normalization phải là object.');
            }
            self::keys($normal, ['trim', 'case_sensitive']);
            foreach ($normal as $value) {
                if (!is_bool($value)) {
                    self::fail('normalization chỉ nhận boolean.');
                }
            }
            if ($type === 'fill_blank' && !str_contains($c['prompt'], '____')) {
                self::fail('Điền từ cần vị trí trống ____ trong prompt.');
            }
            $a = [
                'accepted_answers' => $answers,
                'normalization' => array_replace(
                    ['trim' => true, 'case_sensitive' => false],
                    $normal,
                ),
            ];
        } elseif ($type === 'token_order') {
            $c['tokens'] = self::options($c['tokens'] ?? null, $type);
            self::keys($a, ['accepted_sequences']);
            $sequences = $a['accepted_sequences'] ?? null;
            if (
                !is_array($sequences) ||
                !array_is_list($sequences) ||
                count($sequences) < 1 ||
                count($sequences) > 10
            ) {
                self::fail('accepted_sequences phải có 1–10 thứ tự.');
            }
            $expected = array_column($c['tokens'], 'id');
            sort($expected);
            foreach ($sequences as $seq) {
                $seq = self::strings($seq, 2, 30);
                sort($seq);
                if ($seq !== $expected) {
                    self::fail(
                        'Thứ tự đáp án phải chứa đủ mỗi token đúng một lần.',
                    );
                }
            }
            $a = ['accepted_sequences' => $sequences];
        } else {
            $c['left'] = self::options($c['left'] ?? null, $type);
            $c['right'] = self::options($c['right'] ?? null, $type);
            self::keys($a, ['pairs']);
            $pairs = $a['pairs'] ?? null;
            if (
                !is_array($pairs) ||
                !array_is_list($pairs) ||
                count($pairs) !== count($c['left']) ||
                count($pairs) !== count($c['right'])
            ) {
                self::fail('Số cặp phải khớp hai cột.');
            }
            $left = [];
            $right = [];
            $words = [];
            foreach ($pairs as &$pair) {
                if (!is_array($pair)) {
                    self::fail('Cặp đáp án không hợp lệ.');
                }
                self::keys($pair, ['left_id', 'right_id', 'word_id']);
                $pair = [
                    'left_id' => self::text($pair, 'left_id', 50),
                    'right_id' => self::text($pair, 'right_id', 50),
                    'word_id' => self::id($pair['word_id'] ?? null),
                ];
                $left[] = $pair['left_id'];
                $right[] = $pair['right_id'];
                $words[] = $pair['word_id'];
            }
            unset($pair);
            foreach (
                [
                    [$left, array_column($c['left'], 'id')],
                    [$right, array_column($c['right'], 'id')],
                ]
                as [$got, $expected]
            ) {
                sort($got);
                sort($expected);
                if ($got !== $expected) {
                    self::fail('Cặp đáp án thiếu, trùng hoặc sai ID.');
                }
            }
            $words = array_values(array_unique($words));
            sort($words);
            if ($words !== $c['target_word_ids']) {
                self::fail('word_id của các cặp phải khớp target_word_ids.');
            }
            $a = ['pairs' => $pairs];
        }
        $wordId =
            ($b['word_id'] ?? '') === '' || ($b['word_id'] ?? null) === null
                ? null
                : self::id($b['word_id']);
        if (
            $wordId !== null &&
            !in_array($wordId, $c['target_word_ids'], true)
        ) {
            self::fail('word_id phải nằm trong target_word_ids.');
        }
        return [
            'lesson_id' => self::id($b['lesson_id'] ?? null),
            'word_id' => $wordId,
            'type' => $type,
            'difficulty' => self::number($b['difficulty'] ?? 1, 1, 5),
            'is_active' => self::number($b['is_active'] ?? 0, 0, 1),
            'content_json' => json_encode(
                $c,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
            ),
            'answer_json' => json_encode(
                $a,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
            ),
        ];
    }
}
