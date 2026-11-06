<?php
declare(strict_types=1);

namespace App\Modules\Learning\Controllers;

use App\Core\{ApiException, Controller, Database, Session};
use App\Middleware\Auth;
use App\Modules\Learning\Repositories\{
    LearningRepository,
    GuestClaimRepository
};
use App\Modules\Learning\Services\{
    GuestLearningService,
    LearningService
};

final class LearningStudioController extends Controller
{
    private const CATEGORIES = [
        'drinks' => 'Đồ uống và giao tiếp',
        'transport' => 'Giao thông',
        'countries' => 'Quốc gia',
        'animals' => 'Động vật',
    ];

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

    private function page(string $view, array $data): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $this->render($view, $data + [
            'basePath' => $this->base(),
            'csrfToken' => Session::csrfToken(),
        ]);
    }

    public function explore(array $params = []): void
    {
        $this->open('explore');
    }

    public function practice(array $params = []): void
    {
        $this->open('practice');
    }

    public function launch(array $params = []): void
    {
        $mode = $_GET['mode'] ?? 'words';

        if (
            !is_string($mode)
            || !in_array($mode, ['words', 'practice'], true)
        ) {
            throw new ApiException(
                'VALIDATION_FAILED',
                'Chế độ không hợp lệ.',
                422
            );
        }

        header(
            'Location: '
                . $this->base()
                . '/learning/'
                . ($mode === 'words' ? 'explore' : 'practice'),
            true,
            302
        );
    }

    private function queryId(string $key): ?int
    {
        if (!array_key_exists($key, $_GET)) {
            return null;
        }

        $id = filter_var(
            $_GET[$key],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            throw new ApiException(
                'VALIDATION_FAILED',
                'Lựa chọn không hợp lệ.',
                422
            );
        }

        return (int) $id;
    }

    private function open(string $screen): void
    {
        $data = [
            'studioMode' => $screen,
            'isGuest' => true,
        ];

        try {
            Session::start();

            $user = Session::userId() === null
                ? null
                : Auth::user();

            $data['isGuest'] = $user === null;

            $db = Database::connection();
            $repo = new LearningRepository($db);

            $guest = new GuestLearningService(
                $repo,
                new GuestClaimRepository($db)
            );

            $topics = $repo->getActiveTopics();
            $lessons = $repo->getActiveLessons();

            $firstId = (int) (
                $guest->firstLesson()['id'] ?? 0
            );

            $guestState = $user === null
                ? $guest->state()
                : null;

            $completed = $user !== null
                ? $repo->completedLessons((int) $user['id'])
                : (
                    ($guestState['status'] ?? '') === 'completed'
                        ? [$firstId]
                        : []
                );

            $pendingGuest = $user !== null
                && $guest->pending();

            $groups = [];
            $byId = [];

            foreach ($lessons as $lesson) {
                $id = (int) $lesson['id'];

                $lesson['unlocked'] = $user !== null
                    ? $repo->unlocked((int) $user['id'], $lesson)
                    : $id === $firstId;

                $lesson['completed'] = in_array(
                    $id,
                    $completed,
                    true
                );

                $groups[(int) $lesson['topic_id']][] = $lesson;
                $byId[$id] = $lesson;
            }

            $topicId = $this->queryId('topic');

            if ($topicId === null && isset($_GET['category'])) {
                $category = $_GET['category'];

                if (
                    !is_string($category)
                    || !isset(self::CATEGORIES[$category])
                ) {
                    throw new ApiException(
                        'VALIDATION_FAILED',
                        'Chủ đề không hợp lệ.',
                        422
                    );
                }

                foreach ($topics as $topic) {
                    if (
                        $topic['name']
                        === self::CATEGORIES[$category]
                    ) {
                        $topicId = (int) $topic['id'];
                        break;
                    }
                }

                if ($topicId === null) {
                    throw new ApiException(
                        'TOPIC_NOT_FOUND',
                        'Chủ đề chưa có dữ liệu.',
                        404
                    );
                }
            }

            $selected = null;

            foreach ($topics as $topic) {
                if ((int) $topic['id'] === $topicId) {
                    $selected = $topic;
                    break;
                }
            }

            if ($topicId !== null && $selected === null) {
                throw new ApiException(
                    'TOPIC_NOT_FOUND',
                    'Không tìm thấy chủ đề.',
                    404
                );
            }

            $data += [
                'topics' => $topics,
                'groups' => $groups,
                'selectedTopic' => $selected,
                'pendingGuest' => $pendingGuest,
                'guestCompleted' =>
                    ($guestState['status'] ?? '') === 'completed',
            ];

            $lessonId = $screen === 'practice'
                ? $this->queryId('lesson')
                : null;

            if ($lessonId !== null && !$pendingGuest) {
                if (!isset($byId[$lessonId])) {
                    throw new ApiException(
                        'LESSON_NOT_FOUND',
                        'Không tìm thấy bài luyện tập.',
                        404
                    );
                }

                $page = $user === null
                    ? $guest->lessonPage($lessonId)
                    : (new LearningService($repo))
                        ->getLessonFlashcards(
                            $lessonId,
                            (int) $user['id']
                        );

                $topicName = '';

                foreach ($topics as $topic) {
                    if (
                        (int) $topic['id']
                        === (int) $page['lesson']['topic_id']
                    ) {
                        $topicName = (string) $topic['name'];
                        break;
                    }
                }

                $shortName = preg_replace(
                    '/^Ải\s+\d+\s*[·:—–-]\s*/u',
                    '',
                    (string) $page['lesson']['name']
                );

                $page['lesson']['name'] =
                    $topicName . ' · ' . $shortName;

                $this->page('learning/player', $page + [
                    'isGuest' => $user === null,
                    'mode' => 'lesson',
                    'initialFocus' => 'practice',
                    'practiceStudio' => true,
                ]);

                return;
            }

            if ($screen === 'explore' && $selected !== null) {
                $words = [];

                foreach (
                    $groups[(int) $selected['id']] ?? []
                    as $lesson
                ) {
                    if (!$lesson['unlocked']) {
                        continue;
                    }

                    foreach (
                        $repo->getWordsByLessonId(
                            (int) $lesson['id']
                        ) as $word
                    ) {
                        $words[(int) $word['id']] = $word;
                    }
                }

                $data['exploreWords'] = array_values($words);
            }

            $this->page('learning/studio', $data);
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

            $this->page('learning/studio', $data + [
                'loadError' => $error->getMessage(),
            ]);
        } catch (\Throwable $error) {
            error_log('[LEARNING-STUDIO] ' . (string) $error);

            http_response_code(500);

            $this->page('learning/studio', $data + [
                'loadError' =>
                    'Chưa tải được dữ liệu. Bạn thử tải lại trang nhé.',
            ]);
        }
    }
}