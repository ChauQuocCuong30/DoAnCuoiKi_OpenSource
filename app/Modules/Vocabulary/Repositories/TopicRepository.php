<?php
declare(strict_types=1);

namespace App\Modules\Vocabulary\Repositories;

use App\Core\ApiException;
use App\Core\Database;
use PDO;
use PDOException;
use Throwable;

final class TopicRepository
{
    // Giữ nguyên hợp đồng đang dùng ở trang tra từ.
    public function activeForSelection(): array
    {
        return Database::connection()->query(
            'SELECT id, name FROM topics WHERE is_active = 1 ORDER BY position, id'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function paginate(string $search, int $page): array
    {
        $db = Database::connection();
        $where = $search === '' ? '' : ' WHERE LOCATE(?, name) > 0';
        $values = $search === '' ? [] : [$search];
        $count = $db->prepare('SELECT COUNT(*) FROM topics' . $where);
        $count->execute($values);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / 20));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * 20;
        $list = $db->prepare(
            'SELECT id, name, position, is_active FROM topics' . $where .
            ' ORDER BY position, id LIMIT 20 OFFSET ' . $offset
        );
        $list->execute($values);
        return ['items' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => $total,
            'page' => $page, 'pages' => $pages];
    }

    public function find(int $id): array
    {
        $query = Database::connection()->prepare('SELECT * FROM topics WHERE id = ?');
        $query->execute([$id]);
        $topic = $query->fetch(PDO::FETCH_ASSOC);
        if ($topic === false) {
            throw new ApiException('TOPIC_NOT_FOUND', 'Không tìm thấy chủ đề.', 404);
        }
        return $topic;
    }

    public function save(?int $id, array $data): int
    {
        $db = Database::connection();
        // Ignore any manual ID/position supplied by older forms or other callers.
        $values = array_intersect_key($data, array_flip(['name', 'description', 'image', 'is_active']));
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $db->beginTransaction();
            try {
                if ($id !== null) {
                    $lock = $db->prepare('SELECT id FROM topics WHERE id = ? FOR UPDATE');
                    $lock->execute([$id]);
                    if ($lock->fetchColumn() === false) {
                        throw new ApiException('TOPIC_NOT_FOUND', 'Chủ đề không còn tồn tại.', 404);
                    }
                    $query = $db->prepare(
                        'UPDATE topics SET name = :name, description = :description,
                         image = :image, is_active = :is_active WHERE id = :id'
                    );
                    $query->execute($values + ['id' => $id]);
                    $savedId = $id;
                } else {
                    // Lock the end of the unique position index, including hidden topics.
                    // The unique constraint and bounded retry also cover an initially empty table.
                    $last = $db->query('SELECT position FROM topics ORDER BY position DESC LIMIT 1 FOR UPDATE')->fetchColumn();
                    $position = $last === false ? 1 : (int) $last + 1;
                    if ($position > 2147483647) {
                        throw new ApiException('TOPIC_ORDER_LIMIT', 'Không còn vị trí trống để thêm chủ đề.', 409);
                    }
                    $query = $db->prepare(
                        'INSERT INTO topics (name, description, image, position, is_active)
                         VALUES (:name, :description, :image, :position, :is_active)'
                    );
                    $query->execute($values + ['position' => $position]);
                    $savedId = (int) $db->lastInsertId();
                }
                $db->commit();
                // LIÊN QUAN DANH/TUẤN: không xóa từ/bài học/lịch sử khi ẩn chủ đề.
                return $savedId;
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                if ($e instanceof PDOException && in_array((int) ($e->errorInfo[1] ?? 0), [1062, 1205, 1213], true)) {
                    if ($attempt < 2) continue;
                    throw new ApiException('TOPIC_SAVE_BUSY', 'Có người khác đang cập nhật chủ đề. Hãy thử lưu lại.', 409);
                }
                throw $e;
            }
        }
        throw new ApiException('TOPIC_SAVE_BUSY', 'Không thể lưu chủ đề lúc này.', 409);
    }
}
