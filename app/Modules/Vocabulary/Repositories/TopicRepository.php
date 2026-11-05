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
                     image = :image, position = :position, is_active = :is_active WHERE id = :id'
                );
                $query->execute($data + ['id' => $id]);
            } else {
                $query = $db->prepare(
                    'INSERT INTO topics (name, description, image, position, is_active)
                     VALUES (:name, :description, :image, :position, :is_active)'
                );
                $query->execute($data);
                $id = (int) $db->lastInsertId();
            }
            $db->commit();
            // LIÊN QUAN DANH/TUẤN: không xóa từ/bài học/lịch sử khi ẩn chủ đề.
            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new ApiException('POSITION_EXISTS', 'Thứ tự này đã có chủ đề khác sử dụng.', 409);
            }
            throw $e;
        }
    }
}
