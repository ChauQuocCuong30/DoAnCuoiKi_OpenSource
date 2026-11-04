<?php
namespace App\Modules\Learning\Services;

use App\Modules\Learning\Repositories\LearningRepository;

class LearningService {
    private $learningRepo;

    public function __construct() {
        $this->learningRepo = new LearningRepository();
    }

    public function getLearningPathData() {
        $topics = $this->learningRepo->getActiveTopics();
        $lessons = $this->learningRepo->getActiveLessons();
        
        $lessonsByTopic = [];
        foreach ($lessons as $lesson) {
            $lessonsByTopic[$lesson['topic_id']][] = $lesson;
        }
        
        return [
            'topics' => $topics,
            'lessonsByTopic' => $lessonsByTopic
        ];
    }

    public function getLessonFlashcards($lessonId) {
        // Lấy danh sách từ vựng từ Repository
        $words = $this->learningRepo->getWordsByLessonId($lessonId);
        
        return [
            'lesson_id' => $lessonId,
            'words' => $words
        ];
    } 
}