<?php
declare(strict_types=1);

namespace App\Modules\Learning\Services;

use App\Core\ApiException;

final class ExerciseService
{
    private const SELECT = ['choose_meaning', 'choose_image', 'listen_select', 'sound_discrimination'];
    private const TEXT = ['fill_blank', 'translate_text', 'listen_type'];

    public static function media(mixed $value): ?string
    {
        if (!is_string($value) || preg_match('/[\x00-\x20<>"\'\\\\]/', $value)) {
            return null;
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            return $value;
        }
        return filter_var($value, FILTER_VALIDATE_URL) && parse_url($value, PHP_URL_SCHEME) === 'https'
            ? $value : null;
    }

    /** Validate database content and keep an immutable private snapshot. Invalid content is unavailable. */
    public function snapshot(array $exercise, array $words): ?array
    {
        try {
            $content = json_decode($exercise['content_json'], true, 512, JSON_THROW_ON_ERROR);
            $answer = json_decode($exercise['answer_json'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($content) || !is_array($answer)) { return null; }
            $targets = $content['target_word_ids'] ?? [];
            if (!is_array($targets) || $targets === []) { return null; }
            $allowed = array_column($words, null, 'id');
            foreach ($targets as $target) {
                if (!is_int($target) || !isset($allowed[$target])) { return null; }
            }
            if ($exercise['word_id'] !== null && !in_array((int) $exercise['word_id'], $targets, true)) {
                return null;
            }
            foreach (['instruction', 'prompt'] as $field) {
                if (isset($content[$field]) && !is_string($content[$field])) { return null; }
            }
            $type = $exercise['type'];
            $public = ['instruction' => (string) ($content['instruction'] ?? ''),
                'prompt' => (string) ($content['prompt'] ?? '')];
            foreach (['prompt_lang', 'answer_lang', 'left_lang', 'right_lang'] as $field) {
                if (isset($content[$field]) && in_array($content[$field], ['en', 'vi'], true)) { $public[$field] = $content[$field]; }
            }
            if (isset($content['prompt_audio']) && ($url = self::media($content['prompt_audio'])) !== null) { $public['prompt_audio'] = $url; }
            if (in_array($type, self::SELECT, true)) {
                $options = $this->items($content['options'] ?? [], $type === 'choose_image' ? 'image' : 'text');
                $correct = $answer['correct_option_ids'] ?? [];
                if ($options === null || count($options) < 2 || !is_array($correct) || count($correct) !== 1
                    || !in_array($correct[0], array_column($options, 'id'), true)) { return null; }
                $public['options'] = $options;
            } elseif (in_array($type, self::TEXT, true)) {
                if (empty($answer['accepted_answers']) || !is_array($answer['accepted_answers'])) { return null; }
                if (isset($answer['normalization']) && !is_array($answer['normalization'])) { return null; }
                foreach (['trim', 'case_sensitive'] as $flag) {
                    if (isset($answer['normalization'][$flag]) && !is_bool($answer['normalization'][$flag])) { return null; }
                }
                foreach ($answer['accepted_answers'] as $text) {
                    if (!is_string($text) || trim($text) === '') { return null; }
                }
            } elseif ($type === 'token_order') {
                $items = $this->items($content['tokens'] ?? [], 'text');
                if ($items === null || empty($answer['accepted_sequences']) || !is_array($answer['accepted_sequences'])) { return null; }
                $ids = array_column($items, 'id'); sort($ids);
                foreach ($answer['accepted_sequences'] as $sequence) {
                    if (!is_array($sequence)) { return null; }
                    sort($sequence);
                    if ($ids !== $sequence) { return null; }
                }
                $public['tokens'] = $items;
            } elseif ($type === 'match_pairs') {
                $left = $this->items($content['left'] ?? [], 'text');
                $right = $this->items($content['right'] ?? [], 'text');
                $pairs = $answer['pairs'] ?? [];
                if ($left === null || $right === null || !is_array($pairs) || count($pairs) !== count($left)
                    || count($left) !== count($right)) { return null; }
                $seenLeft = $seenRight = [];
                foreach ($pairs as $pair) {
                    if (!in_array($pair['left_id'] ?? null, array_column($left, 'id'), true)
                        || !in_array($pair['right_id'] ?? null, array_column($right, 'id'), true)
                        || !in_array($pair['word_id'] ?? null, $targets, true)
                        || isset($seenLeft[$pair['left_id']]) || isset($seenRight[$pair['right_id']])) { return null; }
                    $seenLeft[$pair['left_id']] = $seenRight[$pair['right_id']] = true;
                }
                $public['left'] = $left; $public['right'] = $right;
            } else { return null; }
            if (in_array($type, ['listen_select', 'listen_type', 'sound_discrimination'], true)) {
                $audio = self::media($content['audio'] ?? null);
                if ($audio === null) { return null; }
                $public['audio'] = $audio;
            }
            // Private immutable targets for the lesson -> SM-2 bridge; not part of public content.
            $snapshot = ['type' => $type, 'content' => $public, 'answer' => $answer,
                'target_word_ids' => array_values(array_unique($targets))];
            $snapshot['content'] = $this->publicContent($snapshot, $words);
            return $snapshot;
        } catch (\JsonException | \TypeError $e) { return null; }
    }

    /** A text equivalent for missing audio; never expose accepted answers or option keys. */
    public function publicContent(array $snapshot, array $words): array
    {
        $content = $snapshot['content'];
        if (isset($content['media_fallback'])) { return $content; }
        if (!in_array($snapshot['type'], ['listen_type', 'listen_select'], true)) { return $content; }
        $word = null;
        foreach ($words as $candidate) {
            if (in_array((int) $candidate['id'], $snapshot['target_word_ids'], true)) { $word = $candidate; break; }
        }
        if ($word === null || trim((string) ($word['vietnamese'] ?? '')) === '') { return $content; }
        if ($snapshot['type'] === 'listen_type') {
            // Only word dictation can use a word translation; sentence dictation must keep its own clue.
            $answers = array_map(static fn ($v) => mb_strtolower(trim($v)), $snapshot['answer']['accepted_answers'] ?? []);
            if (!in_array(mb_strtolower(trim($word['english'])), $answers, true)) { return $content; }
            $content['media_fallback'] = ['type' => 'translate_text', 'instruction' => 'Viết từ tiếng Anh',
                'prompt' => $word['vietnamese'], 'prompt_lang' => 'vi', 'answer_lang' => 'en'];
        } else {
            $correct = $snapshot['answer']['correct_option_ids'][0] ?? null;
            foreach ($content['options'] ?? [] as $option) {
                if ($option['id'] === $correct && mb_strtolower(trim($option['text'])) === mb_strtolower(trim($word['vietnamese']))) {
                    $content['media_fallback'] = ['type' => 'choose_meaning', 'instruction' => 'Chọn nghĩa tiếng Việt',
                        'prompt' => $word['english'], 'prompt_lang' => 'en', 'answer_lang' => 'vi'];
                    break;
                }
            }
        }
        return $content;
    }

    private function items(mixed $items, string $field): ?array
    {
        if (!is_array($items) || $items === []) { return null; }
        $result = []; $seen = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['id'] ?? null) || $item['id'] === ''
                || isset($seen[$item['id']]) || !is_string($item[$field] ?? null) || $item[$field] === '') { return null; }
            if ($field === 'image' && self::media($item[$field]) === null) { return null; }
            $seen[$item['id']] = true;
            $result[] = ['id' => $item['id'], $field => $item[$field]];
            $last = array_key_last($result);
            if (in_array($item['lang'] ?? null, ['en', 'vi'], true)) { $result[$last]['lang'] = $item['lang']; }
            if (($audio = self::media($item['audio'] ?? null)) !== null) { $result[$last]['audio'] = $audio; }
        }
        return $result;
    }

    /** Returns a canonical answer; rejects malformed answers without spending a heart. */
    public function normalize(array $snapshot, mixed $input): array
    {
        $invalid = static fn () => new ApiException('VALIDATION_FAILED', 'Câu trả lời không hợp lệ.', 422);
        if (!is_array($input)) { throw $invalid(); }
        $type = $snapshot['type']; $content = $snapshot['content'];
        if (in_array($type, self::SELECT, true)) {
            $id = $input['option_id'] ?? null;
            if (!is_string($id) || !in_array($id, array_column($content['options'], 'id'), true)) { throw $invalid(); }
            return ['option_id' => $id];
        }
        if (in_array($type, self::TEXT, true)) {
            $text = $input['text'] ?? null;
            if (!is_string($text) || trim($text) === '' || mb_strlen($text) > 1000) { throw $invalid(); }
            return ['text' => $text];
        }
        if ($type === 'token_order') {
            $sequence = $input['token_ids'] ?? null;
            if (!is_array($sequence) || !array_is_list($sequence)) { throw $invalid(); }
            $sorted = $sequence; sort($sorted); $ids = array_column($content['tokens'], 'id'); sort($ids);
            if ($sorted !== $ids) { throw $invalid(); }
            return ['token_ids' => $sequence];
        }
        if ($type === 'match_pairs') {
            $pairs = $input['pairs'] ?? null;
            if (!is_array($pairs) || count($pairs) !== count($content['left'])) { throw $invalid(); }
            $normalized = []; $seenLeft = $seenRight = [];
            foreach ($pairs as $pair) {
                if (!is_array($pair) || !in_array($pair['left_id'] ?? null, array_column($content['left'], 'id'), true)
                    || !in_array($pair['right_id'] ?? null, array_column($content['right'], 'id'), true)
                    || isset($seenLeft[$pair['left_id']]) || isset($seenRight[$pair['right_id']])) { throw $invalid(); }
                $seenLeft[$pair['left_id']] = $seenRight[$pair['right_id']] = true;
                $normalized[] = ['left_id' => $pair['left_id'], 'right_id' => $pair['right_id']];
            }
            usort($normalized, static fn ($a, $b) => strcmp($a['left_id'], $b['left_id']));
            return ['pairs' => $normalized];
        }
        throw $invalid();
    }

    public function grade(array $snapshot, array $input): bool
    {
        $answer = $snapshot['answer']; $type = $snapshot['type'];
        if (in_array($type, self::SELECT, true)) {
            return in_array($input['option_id'], $answer['correct_option_ids'], true);
        }
        if (in_array($type, self::TEXT, true)) {
            $norm = static function (string $text) use ($answer): string {
                if ($answer['normalization']['trim'] ?? true) { $text = trim($text); }
                return ($answer['normalization']['case_sensitive'] ?? false) ? $text : mb_strtolower($text, 'UTF-8');
            };
            return in_array($norm($input['text']), array_map($norm, $answer['accepted_answers']), true);
        }
        if ($type === 'token_order') {
            $tokens=array_column($snapshot['content']['tokens'],'text','id');
            $text=static fn($sequence)=>array_map(static fn($id)=>$tokens[$id],$sequence);
            return in_array($text($input['token_ids']),array_map($text,$answer['accepted_sequences']),true);
        }
        $expected = array_map(static fn ($pair) => ['left_id' => $pair['left_id'], 'right_id' => $pair['right_id']], $answer['pairs']);
        usort($expected, static fn ($a, $b) => strcmp($a['left_id'], $b['left_id']));
        return $input['pairs'] === $expected;
    }

    /** Reveal corrections only after the server has accepted and graded this submission. */
    public function feedback(array $snapshot, array $input): array
    {
        $content = $snapshot['content']; $answer = $snapshot['answer'];
        if (isset($content['options'])) {
            $options = array_map(static fn($o) => ['id'=>$o['id'], 'is_correct'=>in_array($o['id'],$answer['correct_option_ids'],true)], $content['options']);
            $texts = array_map(static fn($o) => $o['text'] ?? 'Hình minh họa', array_filter($content['options'], static fn($o) => in_array($o['id'],$answer['correct_option_ids'],true)));
            return ['correct_text'=>implode(', ', $texts), 'option_results'=>$options];
        }
        if ($snapshot['type'] === 'token_order') {
            $tokens = array_column($content['tokens'], 'text', 'id');
            return ['correct_text'=>implode(' ', array_map(static fn($id) => $tokens[$id], $answer['accepted_sequences'][0]))];
        }
        if ($snapshot['type'] === 'match_pairs') {
            $expected = array_column($answer['pairs'], 'right_id', 'left_id');
            return ['pairs'=>array_map(static fn($p) => $p+['is_correct'=>($expected[$p['left_id']] ?? null)===$p['right_id']], $input['pairs'])];
        }
        return ['correct_text'=>$answer['accepted_answers'][0] ?? ''];
    }
}
