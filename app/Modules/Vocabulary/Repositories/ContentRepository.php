<?php
declare(strict_types=1);
namespace App\Modules\Vocabulary\Repositories;
use App\Core\ApiException;
use App\Core\Database;
use PDO;
use PDOException;
use Throwable;

// Dùng chung cho Dictionary, Admin và CSV. Khóa chủ đề trước khi ghi từ.
// LIÊN QUAN DANH/TUẤN: giữ ID và lịch sử; không tính điểm, XP hoặc SM-2.
final class ContentRepository
{
    private PDO $db;
    private static int $sequence = 0;
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }
    public function rows(string $sql, array $args = []): array
    {
        $s = $this->db->prepare($sql);
        $s->execute($args);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function execute(string $sql, array $args = []): void
    {
        $s = $this->db->prepare($sql);
        $s->execute($args);
    }
    public function books(): array
    {
        // Count all persisted words, including hidden ones; no lesson/history writes.
        return $this->rows(
            'SELECT t.id,t.name,t.description,t.position,t.is_active,
             COALESCE(w.word_count,0) word_count,
             COALESCE(w.active_word_count,0) active_word_count
             FROM topics t LEFT JOIN (
                 SELECT topic_id,COUNT(*) word_count,SUM(is_active=1) active_word_count
                 FROM words GROUP BY topic_id
             ) w ON w.topic_id=t.id ORDER BY t.position,t.id',
        );
    }
    public function transaction(callable $work): mixed
    {
        $nested = $this->db->inTransaction();
        $point = 'content_' . ++self::$sequence;
        if ($nested) {
            $this->db->exec('SAVEPOINT ' . $point);
        } else {
            $this->db->beginTransaction();
        }
        try {
            $result = $work();
            if ($nested) {
                $this->db->exec('RELEASE SAVEPOINT ' . $point);
            } else {
                $this->db->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($nested) {
                $this->db->exec('ROLLBACK TO SAVEPOINT ' . $point);
                $this->db->exec('RELEASE SAVEPOINT ' . $point);
            } elseif ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (
                $e instanceof PDOException &&
                (int) ($e->errorInfo[1] ?? 0) === 1062
            ) {
                throw new ApiException(
                    'DUPLICATE_CONTENT',
                    'Trùng thứ tự hoặc dữ liệu. Kiểm tra lại.',
                    409,
                );
            }
            throw $e;
        }
    }
    private function table(string $kind): string
    {
        if (!in_array($kind, ['words', 'lessons', 'exercises'], true)) {
            throw new ApiException(
                'NOT_FOUND',
                'Loại nội dung không tồn tại.',
                404,
            );
        }
        return $kind;
    }
    public function get(string $kind, int $id): array
    {
        $table = $this->table($kind);
        $rows = $this->rows('SELECT * FROM ' . $table . ' WHERE id=?', [$id]);
        if (!$rows) {
            throw new ApiException(
                'CONTENT_NOT_FOUND',
                'Không tìm thấy nội dung.',
                404,
            );
        }
        $row = $rows[0];
        if ($kind === 'lessons') {
            $row['word_ids'] = array_map(
                'intval',
                array_column(
                    $this->rows(
                        'SELECT word_id FROM lesson_words WHERE lesson_id=? ORDER BY word_id',
                        [$id],
                    ),
                    'word_id',
                ),
            );
        }
        if ($kind === 'words') {
            $meta = $this->rows(
                'SELECT metadata_json FROM word_sources WHERE word_id=?',
                [$id],
            );
            $source = $meta
                ? json_decode(
                    $meta[0]['metadata_json'],
                    true,
                    32,
                    JSON_THROW_ON_ERROR,
                )
                : [];
            $manual = $source['manual'] ?? [];
            $phonetic = $source['selected_phonetic'] ?? [];
            foreach (['image', 'audio'] as $media) {
                $row[$media . '_source'] =
                    $manual[$media . '_source'] ??
                    ($media === 'audio'
                        ? $phonetic['source_url'] ?? ''
                        : $source['image_source'] ?? '');
                $row[$media . '_license'] =
                    $manual[$media . '_license'] ??
                    ($media === 'audio'
                        ? $phonetic['license']['name'] ?? ''
                        : $source['image_license'] ?? '');
            }
        }
        return $row;
    }
    public function listing(
        string $kind,
        string $q,
        int $page,
        ?int $parent = null,
    ): array {
        $table = $this->table($kind);
        $conditions = [];
        $args = [];
        if ($q !== '') {
            $conditions[] =
                $kind === 'words'
                    ? '(LOCATE(?,english)>0 OR LOCATE(?,vietnamese)>0)'
                    : ($kind === 'lessons'
                        ? 'LOCATE(?,name)>0'
                        : 'LOCATE(?,type)>0');
            $args[] = $q;
            if ($kind === 'words') {
                $args[] = $q;
            }
        }
        if ($parent !== null) {
            $conditions[] =
                ($kind === 'exercises' ? 'lesson_id' : 'topic_id') . '=?';
            $args[] = $parent;
        }
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $total = (int) $this->rows(
            'SELECT COUNT(*) n FROM ' . $table . $where,
            $args,
        )[0]['n'];
        $pages = max(1, (int) ceil($total / 20));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * 20;
        $columns = match ($kind) {
            'words'
                => 'id,topic_id,english,vietnamese,part_of_speech,is_active',
            'lessons'
                => 'id,topic_id,name,level,position,question_count,is_active',
            default => 'id,lesson_id,word_id,type,difficulty,is_active',
        };
        return [
            'items' => $this->rows(
                'SELECT ' .
                    $columns .
                    ' FROM ' .
                    $table .
                    $where .
                    ' ORDER BY id DESC LIMIT 20 OFFSET ' .
                    $offset,
                $args,
            ),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }
    public function topic(int $id, bool $active = true): array
    {
        $rows = $this->rows('SELECT * FROM topics WHERE id=? FOR UPDATE', [
            $id,
        ]);
        if (!$rows || ($active && (int) $rows[0]['is_active'] !== 1)) {
            throw new ApiException(
                'INVALID_TOPIC',
                'Chủ đề không tồn tại hoặc đã ẩn.',
                422,
            );
        }
        return $rows[0];
    }
    public function duplicate(
        array $w,
        ?int $except = null,
        bool $lock = false,
    ): ?array {
        $sql =
            'SELECT id,is_active FROM words WHERE topic_id=? AND english=? AND part_of_speech=? AND BINARY LOWER(vietnamese)=BINARY LOWER(?)';
        $args = [
            $w['topic_id'],
            $w['english'],
            $w['part_of_speech'],
            $w['vietnamese'],
        ];
        if ($except !== null) {
            $sql .= ' AND id<>?';
            $args[] = $except;
        }
        return $this->rows(
            $sql . ' LIMIT 1' . ($lock ? ' FOR UPDATE' : ''),
            $args,
        )[0] ?? null;
    }
    public function saveWord(?int $id, array $word, array $source): int
    {
        return $this->transaction(function () use ($id, $word, $source) {
            $word['is_active'] ??= 1;
            $old = $id === null ? null : $this->get('words', $id);
            $topicIds = array_unique([
                $word['topic_id'],
                $old['topic_id'] ?? $word['topic_id'],
            ]);
            sort($topicIds, SORT_NUMERIC);
            foreach ($topicIds as $topicId) {
                $this->topic((int) $topicId, false);
            }
            if ($word['is_active'] === 1) {
                $this->topic((int) $word['topic_id']);
            }
            if ($old) {
                $fresh = $this->rows(
                    'SELECT * FROM words WHERE id=? FOR UPDATE',
                    [$id],
                )[0];
                if ((int) $fresh['topic_id'] !== (int) $old['topic_id']) {
                    throw new ApiException(
                        'CONTENT_CHANGED',
                        'Nội dung vừa thay đổi, hãy mở lại.',
                        409,
                    );
                }
                $old = $fresh;
                if (
                    (int) $old['topic_id'] !== $word['topic_id'] &&
                    $this->rows(
                        'SELECT lesson_id FROM lesson_words WHERE word_id=? LIMIT 1',
                        [$id],
                    )
                ) {
                    throw new ApiException(
                        'WORD_LINKED',
                        'Từ đã gắn bài học; không đổi chủ đề. Hãy tạo từ mới.',
                        409,
                    );
                }
                $semantic = false;
                foreach (['english', 'vietnamese', 'part_of_speech'] as $key) {
                    if ($word[$key] !== $old[$key]) {
                        $semantic = true;
                    }
                }
                if (
                    $semantic &&
                    ($this->rows(
                        'SELECT id FROM user_review_states WHERE word_id=? LIMIT 1 FOR UPDATE',
                        [$id],
                    ) ||
                        $this->rows(
                            'SELECT s.id FROM learning_sessions s JOIN lesson_words lw ON lw.lesson_id=s.lesson_id WHERE lw.word_id=? LIMIT 1 FOR UPDATE',
                            [$id],
                        ))
                ) {
                    throw new ApiException(
                        'WORD_HAS_HISTORY',
                        'Từ đã có lịch sử học; tạo nghĩa mới thay vì thay đổi nghĩa cũ.',
                        409,
                    );
                }
            }
            $duplicate = $this->duplicate($word, $id, true);
            if ($duplicate) {
                throw new ApiException(
                    'WORD_EXISTS',
                    'Từ/nghĩa này đã có trong chủ đề.',
                    409,
                    [
                        'word_id' => (int) $duplicate['id'],
                        'is_active' => (int) $duplicate['is_active'],
                    ],
                );
            }
            $columns = [
                'topic_id',
                'english',
                'vietnamese',
                'part_of_speech',
                'ipa',
                'example',
                'example_translation',
                'image',
                'audio',
                'is_active',
            ];
            $id = $this->persist('words', $id, $word, $columns);
            $oldSource = $this->rows(
                'SELECT metadata_json FROM word_sources WHERE word_id=?',
                [$id],
            );
            if ($oldSource) {
                $existing = json_decode(
                    $oldSource[0]['metadata_json'],
                    true,
                    32,
                    JSON_THROW_ON_ERROR,
                );
                $source = array_replace(
                    $existing,
                    ['last_edit' => $source],
                    isset($source['manual'])
                        ? ['manual' => $source['manual']]
                        : [],
                );
            }
            $this->execute(
                'INSERT INTO word_sources(word_id,metadata_json) VALUES(?,?) ON DUPLICATE KEY UPDATE metadata_json=VALUES(metadata_json)',
                [
                    $id,
                    json_encode(
                        $source,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                    ),
                ],
            );
            return $id;
        });
    }
    private function persist(
        string $table,
        ?int $id,
        array $data,
        array $columns,
    ): int {
        $values = [];
        foreach ($columns as $key) {
            $values[] = $data[$key];
        }
        if ($id === null) {
            $this->execute(
                'INSERT INTO ' .
                    $table .
                    ' (' .
                    implode(',', $columns) .
                    ') VALUES (' .
                    implode(',', array_fill(0, count($columns), '?')) .
                    ')',
                $values,
            );
            return (int) $this->db->lastInsertId();
        }
        $values[] = $id;
        $this->execute(
            'UPDATE ' .
                $table .
                ' SET ' .
                implode(',', array_map(static fn($k) => $k . '=?', $columns)) .
                ' WHERE id=?',
            $values,
        );
        return $id;
    }
    public function saveLesson(?int $id, array $data): int
    {
        return $this->transaction(function () use ($id, $data) {
            $old = $id === null ? null : $this->get('lessons', $id);
            $topics = array_unique([
                $data['topic_id'],
                $old['topic_id'] ?? $data['topic_id'],
            ]);
            sort($topics, SORT_NUMERIC);
            foreach ($topics as $topic) {
                $this->topic((int) $topic, false);
            }
            if ($data['is_active'] === 1) {
                $this->topic($data['topic_id']);
            }
            if ($old) {
                $this->rows('SELECT id FROM lessons WHERE id=? FOR UPDATE', [
                    $id,
                ]);
                if (
                    (int) $old['topic_id'] !== $data['topic_id'] &&
                    $old['word_ids']
                ) {
                    throw new ApiException(
                        'LESSON_LINKED',
                        'Bài đã gắn từ; không chuyển chủ đề.',
                        409,
                    );
                }
            }
            $wordIds = $data['word_ids'];
            if ($wordIds) {
                $rows = $this->rows(
                    'SELECT id,topic_id FROM words WHERE id IN (' .
                        implode(',', array_fill(0, count($wordIds), '?')) .
                        ') FOR UPDATE',
                    $wordIds,
                );
                if (count($rows) !== count($wordIds)) {
                    throw new ApiException(
                        'INVALID_WORD',
                        'Có từ không tồn tại.',
                        422,
                    );
                }
                foreach ($rows as $row) {
                    if ((int) $row['topic_id'] !== $data['topic_id']) {
                        throw new ApiException(
                            'INVALID_WORD',
                            'Từ và bài học phải cùng chủ đề.',
                            422,
                        );
                    }
                }
            }
            if ($old) {
                foreach (
                    $this->rows(
                        'SELECT content_json FROM exercises WHERE lesson_id=? FOR UPDATE',
                        [$id],
                    )
                    as $exercise
                ) {
                    $targets =
                        json_decode(
                            $exercise['content_json'],
                            true,
                            32,
                            JSON_THROW_ON_ERROR,
                        )['target_word_ids'] ?? [];
                    if (array_diff($targets, $wordIds)) {
                        throw new ApiException(
                            'WORD_IN_EXERCISE',
                            'Không bỏ từ đang được câu hỏi tham chiếu. Giữ liên kết và ẩn nội dung khi cần.',
                            409,
                        );
                    }
                }
            }
            $columns = [
                'topic_id',
                'name',
                'level',
                'position',
                'question_count',
                'pass_percent',
                'xp_reward',
                'is_active',
            ];
            $id = $this->persist('lessons', $id, $data, $columns);
            foreach ($wordIds as $word) {
                $this->execute(
                    'INSERT IGNORE INTO lesson_words(lesson_id,word_id) VALUES(?,?)',
                    [$id, $word],
                );
            }
            foreach (array_diff($old['word_ids'] ?? [], $wordIds) as $word) {
                $this->execute(
                    'DELETE FROM lesson_words WHERE lesson_id=? AND word_id=?',
                    [$id, $word],
                );
            }
            if (
                $data['is_active'] === 1 &&
                count($this->mainExercises($id)) < $data['question_count']
            ) {
                throw new ApiException(
                    'LESSON_NOT_READY',
                    'Chưa đủ câu hỏi chính hợp lệ (không tính match_pairs) để hiện bài. Lưu Ẩn, thêm câu hỏi rồi hiện bài.',
                    422,
                );
            }
            return $id;
        });
    }
    public function saveExercise(?int $id, array $data): int
    {
        return $this->transaction(function () use ($id, $data) {
            $old = $id === null ? null : $this->get('exercises', $id);
            $lessonRows = $this->rows(
                'SELECT topic_id FROM lessons WHERE id=?',
                [$data['lesson_id']],
            );
            if (!$lessonRows) {
                throw new ApiException(
                    'INVALID_LESSON',
                    'Bài học không tồn tại.',
                    422,
                );
            }
            $this->topic((int) $lessonRows[0]['topic_id'], false);
            $lesson = $this->rows(
                'SELECT * FROM lessons WHERE id=? FOR UPDATE',
                [$data['lesson_id']],
            )[0];
            if ($old && (int) $old['lesson_id'] !== $data['lesson_id']) {
                throw new ApiException(
                    'EXERCISE_LINKED',
                    'Không chuyển câu hỏi sang bài khác; hãy tạo bản mới.',
                    409,
                );
            }
            if ($old) {
                $this->rows('SELECT id FROM exercises WHERE id=? FOR UPDATE', [
                    $id,
                ]);
                $previous = \App\Modules\Admin\Requests\ContentInput::exercise(
                    $old,
                );
                $changed = false;
                foreach (['word_id', 'type', 'difficulty'] as $key) {
                    if ((string) $previous[$key] !== (string) $data[$key]) {
                        $changed = true;
                    }
                }
                foreach (['content_json', 'answer_json'] as $key) {
                    if (
                        json_decode($previous[$key], true) !==
                        json_decode($data[$key], true)
                    ) {
                        $changed = true;
                    }
                }
                if (
                    $changed &&
                    $this->rows(
                        'SELECT id FROM session_questions WHERE exercise_id=? LIMIT 1 FOR UPDATE',
                        [$id],
                    )
                ) {
                    throw new ApiException(
                        'EXERCISE_HAS_HISTORY',
                        'Câu hỏi đã được dùng. Chỉ đổi ẩn/hiện hoặc tạo câu hỏi mới.',
                        409,
                    );
                }
            }
            $linked = array_map(
                'intval',
                array_column(
                    $this->rows(
                        'SELECT word_id FROM lesson_words WHERE lesson_id=?',
                        [$data['lesson_id']],
                    ),
                    'word_id',
                ),
            );
            $targets = json_decode(
                $data['content_json'],
                true,
                32,
                JSON_THROW_ON_ERROR,
            )['target_word_ids'];
            if (array_diff($targets, $linked)) {
                throw new ApiException(
                    'WORD_NOT_IN_LESSON',
                    'target_word_ids phải được gắn vào bài trước.',
                    422,
                );
            }
            if ($data['is_active'] === 1) {
                $this->topic((int) $lesson['topic_id']);
                $active = array_map(
                    'intval',
                    array_column(
                        $this->rows(
                            'SELECT id FROM words WHERE topic_id=? AND is_active=1',
                            [$lesson['topic_id']],
                        ),
                        'id',
                    ),
                );
                if (array_diff($targets, $active)) {
                    throw new ApiException(
                        'WORD_HIDDEN',
                        'Câu hỏi hiện không được dùng từ đã ẩn.',
                        422,
                    );
                }
            }
            if (
                $id === null &&
                (int) $this->rows(
                    'SELECT COUNT(*) n FROM exercises WHERE lesson_id=?',
                    [$data['lesson_id']],
                )[0]['n'] >= 1000
            ) {
                throw new ApiException(
                    'LESSON_LIMIT',
                    'Tối đa 1000 câu hỏi mỗi bài.',
                    422,
                );
            }
            return $this->persist('exercises', $id, $data, [
                'lesson_id',
                'word_id',
                'type',
                'difficulty',
                'content_json',
                'answer_json',
                'is_active',
            ]);
        });
    }
    public function mainExercises(int $lessonId): array
    {
        // LIÊN QUAN DANH PR #20: ghép cặp là vòng cuối, không tính vào question_count.
        return array_values(array_filter($this->usableExercises($lessonId),
            static fn(array $row): bool => $row['type'] !== 'match_pairs'));
    }
    public function usableExercises(int $lessonId): array
    {
        $active = array_map(
            'intval',
            array_column(
                $this->rows(
                    'SELECT w.id FROM words w JOIN lesson_words lw ON lw.word_id=w.id JOIN topics t ON t.id=w.topic_id WHERE lw.lesson_id=? AND w.is_active=1 AND t.is_active=1',
                    [$lessonId],
                ),
                'id',
            ),
        );
        $result = [];
        foreach (
            $this->rows(
                'SELECT id,lesson_id,word_id,type,difficulty,content_json FROM exercises WHERE lesson_id=? AND is_active=1 ORDER BY id LIMIT 1000',
                [$lessonId],
            )
            as $row
        ) {
            $content = json_decode(
                $row['content_json'],
                true,
                32,
                JSON_THROW_ON_ERROR,
            );
            $targets = $content['target_word_ids'] ?? [];
            if (!$targets || array_diff($targets, $active)) {
                continue;
            }
            // Whitelist public fields; never expose answer_json or unknown metadata.
            $safe = array_intersect_key(
                $content,
                array_flip([
                    'schema_version',
                    'instruction',
                    'prompt',
                    'audio',
                    'image',
                    'target_word_ids',
                    'options',
                    'tokens',
                    'left',
                    'right',
                ]),
            );
            foreach (['options', 'tokens', 'left', 'right'] as $key) {
                if (isset($safe[$key])) {
                    $safe[$key] = array_map(
                        static fn($item) => array_intersect_key(
                            $item,
                            array_flip(['id', 'text', 'image', 'audio']),
                        ),
                        $safe[$key],
                    );
                }
            }
            unset($row['content_json']);
            $row['content'] = $safe;
            $result[] = $row;
        }
        return $result;
    }
    public function activeLesson(int $id): array
    {
        $rows = $this->rows(
            'SELECT l.* FROM lessons l JOIN topics t ON t.id=l.topic_id WHERE l.id=? AND l.is_active=1 AND t.is_active=1',
            [$id],
        );
        if (!$rows) {
            throw new ApiException(
                'LESSON_NOT_FOUND',
                'Bài học không tồn tại hoặc đã ẩn.',
                404,
            );
        }
        return $rows[0];
    }
}
