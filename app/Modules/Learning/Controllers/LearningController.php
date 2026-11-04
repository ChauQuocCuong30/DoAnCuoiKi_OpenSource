<?php
declare(strict_types=1);

namespace App\Modules\Learning\Controllers;

use App\Core\Controller;
use App\Modules\Learning\Services\LearningService;

class LearningController extends Controller {
    private LearningService $learningService;

    public function __construct() {
        $this->learningService = new LearningService();
    }

    public function index(array $params = []): void {
        try {
            $data = $this->learningService->getLearningPathData();
            $this->render('learning/index', $data);
        } catch (\Throwable $e) {
            // In chi tiết lỗi ra màn hình để debug
            header('Content-Type: text/plain; charset=utf-8');
            echo "=== CHI TIẾT LỖI PHP ===\n";
            echo "Thông điệp: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . " (Dòng " . $e->getLine() . ")\n\n";
            echo "Trace:\n" . $e->getTraceAsString();
            exit;
        }
    }

    public function lesson(array $params): void {
        try {
            $id = $params['id'] ?? null;
            
            if (!$id) {
                echo "Thiếu ID bài học.";
                return;
            }

            $data = $this->learningService->getLessonFlashcards($id);
            
            if (empty($data['words'])) {
                echo "Bài học này chưa có từ vựng.";
                return;
            }

            $this->render('learning/flashcard', $data);
        } catch (\Throwable $e) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "=== CHI TIẾT LỖI PHP ===\n";
            echo "Thông điệp: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . " (Dòng " . $e->getLine() . ")\n\n";
            echo "Trace:\n" . $e->getTraceAsString();
            exit;
        } 
    }
}