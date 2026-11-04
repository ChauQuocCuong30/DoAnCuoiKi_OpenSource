<?php
declare(strict_types=1);

namespace App\Modules\Learning\Repositories;

use App\Core\Database;
use PDO;

class LearningRepository {
    public function getActiveTopics(): array {
        $db = Database::connection(); // Gọi đúng tên phương thức trong Database.php
        $stmt = $db->prepare("SELECT * FROM topics");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveLessons(): array {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT * FROM lessons WHERE is_active = 1 ORDER BY position ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getWordsByLessonId(int $lessonId): array {
        $db = Database::connection();
        $sql = "SELECT w.* FROM words w 
                JOIN lesson_words lw ON w.id = lw.word_id 
                WHERE lw.lesson_id = :lesson_id";
        $stmt = $db->prepare($sql);
        $stmt->execute(['lesson_id' => $lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}