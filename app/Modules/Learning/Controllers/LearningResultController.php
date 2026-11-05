<?php
declare(strict_types=1);

namespace App\Modules\Learning\Controllers;

use App\Core\{
    ApiException,
    Controller,
    Database,
    Session
};

use App\Middleware\Auth;

use App\Modules\Learning\Repositories\{
    LearningRepository,
    GuestClaimRepository
};

use App\Modules\Learning\Services\{
    ExerciseService,
    GuestLearningService
};

use PDO;

final class LearningResultController extends Controller
{
    private function base(): string
    {
        return rtrim(
            str_replace(
                '\\',
                '/',
                dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')
            ),
            '/'
        );
    }

    public function show(array $params): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, no-store');

        try {
            $id = filter_var(
                $params['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if (!$id) {
                throw new ApiException(
                    'VALIDATION_FAILED',
                    'ID bài không hợp lệ.',
                    422
                );
            }

            $db = Database::connection();
            $repo = new LearningRepository($db);

            $user = Session::userId() === null
                ? null
                : Auth::user();

            $data = $user === null
                ? (new GuestLearningService(
                    $repo,
                    new GuestClaimRepository($db)
                ))->review($id)
                : $this->accountData(
                    $db,
                    $repo,
                    (int) $user['id'],
                    $id
                );

            $data['questions'] = $this->prepareQuestions(
                $data['questions'],
                $this->visibleExercises($repo, $id)
            );

            $data['topicName'] = '';

            foreach ($repo->getActiveTopics() as $topic) {
                if (
                    (int) $topic['id']
                    === (int) $data['lesson']['topic_id']
                ) {
                    $data['topicName'] = (string) $topic['name'];
                    break;
                }
            }

            $data['result']['accuracy'] =
                $data['result']['total'] > 0
                    ? (int) round(
                        100
                        * $data['result']['correct']
                        / $data['result']['total']
                    )
                    : 0;

            $data['result']['finished_label'] = '';

            if (!empty($data['result']['finished_at'])) {
                try {
                    $date = new \DateTimeImmutable(
                        $data['result']['finished_at'],
                        new \DateTimeZone('UTC')
                    );

                    $data['result']['finished_label'] =
                        $date->setTimezone(
                            new \DateTimeZone(
                                $user['timezone']
                                    ?? 'Asia/Ho_Chi_Minh'
                            )
                        )->format('H:i · d/m/Y');
                } catch (\Exception) {
                    // Không hiển thị thời gian nếu dữ liệu không hợp lệ.
                }
            }

            $this->render(
                'learning/result',
                $data + [
                    'basePath' => $this->base(),
                ]
            );
        } catch (ApiException $error) {
            if ($error->status === 401) {
                header(
                    'Location: '
                    . $this->base()
                    . '/login?next=guest',
                    true,
                    302
                );

                return;
            }

            http_response_code($error->status);

            $this->render('learning/result', [
                'basePath' => $this->base(),
                'loadError' => $error->getMessage(),
                'isGuest' => Session::userId() === null,
            ]);
        } catch (\Throwable $error) {
            $ref = 'RESULT-' . strtoupper(bin2hex(random_bytes(6)));

            error_log('[' . $ref . '] ' . (string) $error);
            http_response_code(500);

            $this->render('learning/result', [
                'basePath' => $this->base(),
                'loadError' => 'Chưa tải được kết quả. Bạn thử lại nhé.',
                'errorReference' => $ref,
                'isGuest' => Session::userId() === null,
            ]);
        }
    }

    private function accountData(
        PDO $db,
        LearningRepository $repo,
        int $userId,
        int $lessonId
    ): array {
        $lesson = $repo->lesson($lessonId);

        if ($lesson === null) {
            throw new ApiException(
                'LESSON_NOT_FOUND',
                'Bài học hiện không còn được mở.',
                404
            );
        }

        $query = $db->prepare(
            "SELECT id,total_questions,correct_count,finished_at
             FROM learning_sessions
             WHERE user_id=? AND lesson_id=?
               AND mode='lesson'
               AND status='completed'
             ORDER BY finished_at DESC,id DESC
             LIMIT 1"
        );

        $query->execute([$userId, $lessonId]);
        $session = $query->fetch(PDO::FETCH_ASSOC);

        if ($session === false) {
            throw new ApiException(
                'RESULT_NOT_FOUND',
                'Ải này chưa có lượt hoàn thành để xem lại.',
                404
            );
        }

        $query = $db->prepare(
            "SELECT
                q.position,
                q.exercise_id,
                q.snapshot_json,
                a.answer_json AS first_answer_json,
                a.is_correct AS first_is_correct
             FROM session_questions q
             JOIN learning_sessions s
                ON s.id=q.session_id
             LEFT JOIN answer_submissions a
                ON a.session_question_id=q.id
               AND a.attempt_no=1
             WHERE s.id=?
               AND s.user_id=?
               AND s.lesson_id=?
               AND s.mode='lesson'
               AND s.status='completed'
               AND q.phase='main'
             ORDER BY q.position"
        );

        $query->execute([
            (int) $session['id'],
            $userId,
            $lessonId,
        ]);

        $questions = $query->fetchAll(PDO::FETCH_ASSOC);

        $query = $db->prepare(
            "SELECT COALESCE(SUM(amount),0)
             FROM xp_transactions
             WHERE session_id=?
               AND user_id=?
               AND reason='lesson_completed'"
        );

        $query->execute([
            (int) $session['id'],
            $userId,
        ]);

        $xp = (int) $query->fetchColumn();
        $total = (int) $session['total_questions'];
        $correct = (int) $session['correct_count'];

        return [
            'lesson' => $lesson,
            'isGuest' => false,
            'questions' => $questions,
            'result' => [
                'total' => $total,
                'correct' => $correct,
                'wrong' => max(0, $total - $correct),
                'xp' => $xp,
                'finished_at' => $session['finished_at'],
            ],
        ];
    }

    private function visibleExercises(
        LearningRepository $repo,
        int $lessonId
    ): array {
        $words = $repo->getWordsByLessonId($lessonId);
        $grader = new ExerciseService();
        $visible = [];

        foreach ($repo->exercises($lessonId) as $row) {
            if ($grader->snapshot($row, $words) !== null) {
                $visible[(int) $row['id']] = true;
            }
        }

        return $visible;
    }

    private function prepareQuestions(
        array $rows,
        array $visible
    ): array {
        $questions = [];

        foreach ($rows as $row) {
            $item = [
                'position' => (int) $row['position'],
                'available' => false,
                'correct' => $row['first_is_correct'] === null
                    ? null
                    : (bool) $row['first_is_correct'],
            ];

            if (!isset($visible[(int) $row['exercise_id']])) {
                $questions[] = $item;
                continue;
            }

            try {
                $snapshot = json_decode(
                    $row['snapshot_json'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                $input = $row['first_answer_json'] === null
                    ? null
                    : json_decode(
                        $row['first_answer_json'],
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );

                if (
                    !is_array($snapshot)
                    || !is_array($snapshot['content'] ?? null)
                    || !is_array($snapshot['answer'] ?? null)
                ) {
                    $questions[] = $item;
                    continue;
                }

                $content = $snapshot['content'];

                $item += [
                    'instruction' => (string) (
                        $content['instruction'] ?? 'Câu hỏi'
                    ),
                    'prompt' => (string) ($content['prompt'] ?? ''),
                    'audio' => ExerciseService::media(
                        $content['audio'] ?? null
                    ),
                    'given' => $input === null
                        ? [['text' => 'Chưa có dữ liệu trả lời.']]
                        : $this->describe(
                            $snapshot,
                            $input,
                            false
                        ),
                    'expected' => $this->describe(
                        $snapshot,
                        [],
                        true
                    ),
                ];

                $item['available'] = true;
            } catch (\JsonException | \TypeError) {
                // Bỏ phần chi tiết nếu dữ liệu lưu không hợp lệ.
            }

            $questions[] = $item;
        }

        return $questions;
    }

    private function describe(
        array $snapshot,
        array $input,
        bool $expected
    ): array {
        $content = $snapshot['content'];
        $answer = $snapshot['answer'];
        $type = $snapshot['type'] ?? '';

        if (isset($content['options'])) {
            $ids = $expected
                ? ($answer['correct_option_ids'] ?? [])
                : [$input['option_id'] ?? ''];

            $items = [];

            foreach ($content['options'] as $option) {
                if (in_array($option['id'], $ids, true)) {
                    $items[] = $type === 'choose_image'
                        ? [
                            'image' => ExerciseService::media(
                                $option['image'] ?? null
                            ),
                        ]
                        : [
                            'text' => (string) (
                                $option['text'] ?? ''
                            ),
                        ];
                }
            }

            return $items ?: [
                ['text' => 'Chưa có lựa chọn.'],
            ];
        }

        if ($type === 'token_order') {
            $ids = $expected
                ? ($answer['accepted_sequences'][0] ?? [])
                : ($input['token_ids'] ?? []);

            $tokens = array_column(
                $content['tokens'] ?? [],
                'text',
                'id'
            );

            return [
                [
                    'text' => implode(
                        ' ',
                        array_map(
                            static fn($id) => (string) (
                                $tokens[$id] ?? ''
                            ),
                            $ids
                        )
                    ),
                ],
            ];
        }

        if ($type === 'match_pairs') {
            $left = array_column(
                $content['left'] ?? [],
                'text',
                'id'
            );

            $right = array_column(
                $content['right'] ?? [],
                'text',
                'id'
            );

            $pairs = $expected
                ? ($answer['pairs'] ?? [])
                : ($input['pairs'] ?? []);

            return array_map(
                static fn($pair) => [
                    'text' => ($left[$pair['left_id']] ?? '')
                        . ' → '
                        . ($right[$pair['right_id']] ?? ''),
                ],
                $pairs
            );
        }

        return $expected
            ? array_map(
                static fn($text) => [
                    'text' => (string) $text,
                ],
                $answer['accepted_answers'] ?? []
            )
            : [
                [
                    'text' => (string) ($input['text'] ?? ''),
                ],
            ];
    }
}