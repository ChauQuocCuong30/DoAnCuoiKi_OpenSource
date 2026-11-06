<?php
declare(strict_types=1);
namespace App\Modules\Admin\Services;
use App\Core\ApiException;
use App\Modules\Admin\Requests\ContentInput;
use App\Modules\Vocabulary\Repositories\ContentRepository;

final class ContentCsvImporter
{
    public const HEADERS = [
        'topic_id',
        'cefr_level',
        'english',
        'vietnamese',
        'part_of_speech',
        'ipa',
        'example',
        'example_translation',
        'image',
        'audio',
        'image_source',
        'image_license',
        'audio_source',
        'audio_license',
    ];
    public function __construct(private ContentRepository $repo) {}
    public function preview(string $csv, string $duplicateMode = 'error', ?int $topicId = null, string $defaultLevel = 'A1'): array
    {
        self::duplicateMode($duplicateMode);
        $defaultLevel = ContentInput::cefr($defaultLevel);
        if (strlen($csv) > 1048576) {
            throw new ApiException(
                'CSV_TOO_LARGE',
                'CSV tối đa 1 MiB; chia dữ liệu thành các đợt tối đa 1000 từ.',
                413,
            );
        }
        if (!mb_check_encoding($csv, 'UTF-8')) {
            ContentInput::fail('CSV phải mã hóa UTF-8.');
        }
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $this->syntax($csv);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        try {
            $headers = fgetcsv($stream, 0, ',', '"', '');
            $required = ['english', 'vietnamese', 'part_of_speech', 'example'];
            if ($topicId === null) $required[] = 'topic_id';
            if (!is_array($headers) || array_diff($required, $headers)
                || array_diff($headers, self::HEADERS)
                || count(array_unique($headers)) !== count($headers)) {
                ContentInput::fail(
                    'CSV cần các cột: ' . implode(',', $required) .
                    '. Các cột bổ sung được phép: ' . implode(',', self::HEADERS),
                );
            }
            $rows = [];
            $errors = [];
            $seen = [];
            $record = 1;
            $topics = [];
            $skipped = 0;
            $records = 0;
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $record++;
                if ($values === [null]) {
                    continue;
                }
                if (++$records > 1000) {
                    ContentInput::fail('Mỗi đợt tối đa 1000 bản ghi.');
                }
                try {
                    if (count($values) !== count($headers)) {
                        ContentInput::fail('Thiếu hoặc thừa cột.');
                    }
                    $b = array_combine($headers, $values);
                    if ($topicId !== null) {
                        if (($b['topic_id'] ?? '') !== '' && ContentInput::id($b['topic_id']) !== $topicId) {
                            ContentInput::fail('topic_id trong CSV khác sách đang chọn.');
                        }
                        $b['topic_id'] = $topicId;
                    }
                    $b['cefr_level'] = ($b['cefr_level'] ?? '') !== '' ? $b['cefr_level'] : $defaultLevel;
                    $b['is_active'] = 1;
                    $data = ContentInput::word($b);
                    $w = $data['word'];
                    $rowTopic = $w['topic_id'];
                    $topics[$rowTopic] ??= $this->repo->rows(
                        'SELECT is_active FROM topics WHERE id=?',
                        [$rowTopic],
                    );
                    if (
                        !$topics[$rowTopic] ||
                        (int) $topics[$rowTopic][0]['is_active'] !== 1
                    ) {
                        ContentInput::fail('Chủ đề không tồn tại hoặc đã ẩn.');
                    }
                    $identity = json_encode(
                        [
                            $rowTopic,
                            $w['cefr_level'],
                            mb_strtolower($w['english'], 'UTF-8'),
                            $w['part_of_speech'],
                            mb_strtolower($w['vietnamese'], 'UTF-8'),
                        ],
                        JSON_THROW_ON_ERROR,
                    );
                    if (isset($seen[$identity])) {
                        if ($duplicateMode === 'skip') { $skipped++; continue; }
                        ContentInput::fail(
                            'Trùng bản ghi ' .
                                $seen[$identity] .
                                ' trong file.',
                        );
                    }
                    $seen[$identity] = $record;
                    if ($this->repo->duplicate($w) !== null) {
                        if ($duplicateMode === 'skip') { $skipped++; continue; }
                        ContentInput::fail(
                            'Từ/nghĩa đã có trong database, kể cả từ đã ẩn.',
                        );
                    }
                    $data['source']['provider'] = 'csv_admin';
                    $rows[] = $data;
                } catch (ApiException $e) {
                    $errors[] = [
                        'record' => $record,
                        'message' => $e->getMessage(),
                    ];
                    if (count($errors) >= 100) {
                        break;
                    }
                }
            }
            if (!$rows && !$errors && $skipped === 0) {
                ContentInput::fail('CSV không có dữ liệu.');
            }
            return [
                'rows' => $errors ? [] : $rows,
                'errors' => $errors,
                'valid_count' => $errors ? 0 : count($rows),
                'skipped_count' => $skipped,
            ];
        } finally {
            fclose($stream);
        }
    }
    private function syntax(string $csv): void
    {
        $quoted = false;
        $after = false;
        $start = true;
        $length = strlen($csv);
        for ($i = 0; $i < $length; $i++) {
            $char = $csv[$i];
            if ($char === "\0") {
                ContentInput::fail('CSV chứa ký tự NUL.');
            }
            if ($quoted) {
                if ($char === '"') {
                    if ($i + 1 < $length && $csv[$i + 1] === '"') {
                        $i++;
                    } else {
                        $quoted = false;
                        $after = true;
                    }
                }
                continue;
            }
            if ($after) {
                if ($char !== ',' && $char !== "\r" && $char !== "\n") {
                    ContentInput::fail(
                        'Ký tự không hợp lệ sau dấu đóng ngoặc kép CSV.',
                    );
                }
                $after = false;
            }
            if ($char === '"') {
                if (!$start) {
                    ContentInput::fail(
                        'Dấu ngoặc kép phải bao trọn trường CSV.',
                    );
                }
                $quoted = true;
                $start = false;
            } elseif ($char === ',' || $char === "\r" || $char === "\n") {
                $start = true;
            } else {
                $start = false;
            }
        }
        if ($quoted) {
            ContentInput::fail('CSV thiếu dấu đóng ngoặc kép.');
        }
    }
    public static function duplicateMode(string $mode): string
    {
        if (!in_array($mode, ['error', 'skip'], true)) {
            ContentInput::fail('Chế độ từ trùng phải là error hoặc skip.');
        }
        return $mode;
    }
    public function commit(array $rows, string $duplicateMode = 'error'): array
    {
        self::duplicateMode($duplicateMode);
        if (!$rows || count($rows) > 1000) {
            ContentInput::fail('Bản xem trước không hợp lệ.');
        }
        return $this->repo->transaction(function () use ($rows, $duplicateMode) {
            // Khóa các chủ đề theo cùng thứ tự; mọi ghi CSV là một transaction.
            $topics = array_values(
                array_unique(
                    array_column(array_column($rows, 'word'), 'topic_id'),
                ),
            );
            sort($topics, SORT_NUMERIC);
            foreach ($topics as $id) {
                $this->repo->topic((int) $id);
            }
            $ids = [];
            $skipped = 0;
            foreach ($rows as $row) {
                // Recheck after topic locks: another import may have committed since preview.
                if ($duplicateMode === 'skip' && $this->repo->duplicate($row['word'], null, true) !== null) {
                    $skipped++;
                    continue;
                }
                $ids[] = $this->repo->saveWord(
                    null,
                    $row['word'],
                    $row['source'],
                );
            }
            return ['imported' => count($ids), 'skipped' => $skipped, 'word_ids' => $ids];
        });
    }
}
