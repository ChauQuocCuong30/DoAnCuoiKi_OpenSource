<?php
declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Core\ApiException;
use App\Modules\Admin\Requests\ContentInput;

/** Small, local, bilingual packs. Never look up or translate individual words remotely. */
final class VocabularySamples
{
    private function packs(): array
    {
        $path = dirname(__DIR__, 4) . '/database/samples/vocabulary_samples.json';
        return json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    }

    public function catalog(): array
    {
        return array_map(static fn (array $pack): array => [
            'key' => $pack['key'], 'name' => $pack['name'],
            'count' => count($pack['words']), 'level' => $pack['level'],
        ], $this->packs());
    }

    public function csv(string $key, int $topicId): string
    {
        foreach ($this->packs() as $pack) {
            if ($pack['key'] !== $key) continue;
            $stream = fopen('php://temp', 'w+');
            try {
                $headers = ['topic_id', 'english', 'vietnamese', 'part_of_speech', 'example', 'example_translation'];
                fputcsv($stream, $headers, ',', '"', '');
                foreach ($pack['words'] as $row) {
                    // Validate bundled content through exactly the same rules as manual input.
                    ContentInput::word(['topic_id' => $topicId] + $row);
                    fputcsv($stream, array_map(static fn ($field) => $field === 'topic_id' ? $topicId : ($row[$field] ?? ''), $headers), ',', '"', '');
                }
                rewind($stream);
                return (string) stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
        }
        throw new ApiException('SAMPLE_NOT_FOUND', 'Không tìm thấy bộ từ mẫu.', 404);
    }
}
