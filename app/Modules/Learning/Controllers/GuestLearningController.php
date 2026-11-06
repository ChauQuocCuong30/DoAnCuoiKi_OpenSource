<?php
declare(strict_types=1);

namespace App\Modules\Learning\Controllers;

use App\Core\{
    ApiException,
    Controller,
    Database,
    Request,
    Response,
    Session
};

use App\Middleware\Auth;

use App\Modules\Learning\Repositories\{
    LearningRepository,
    GuestClaimRepository
};

use App\Modules\Learning\Services\{
    GuestLearningService,
    LearningService
};

final class GuestLearningController extends Controller
{
    private const ENTRY_KEY = 'learning_entry_focus_v1';

    private function repository(): LearningRepository
    {
        return new LearningRepository(Database::connection());
    }

    private function guest(): GuestLearningService
    {
        $db = Database::connection();

        return new GuestLearningService(
            new LearningRepository($db),
            new GuestClaimRepository($db)
        );
    }

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

    private function page(string $view, array $data = []): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $this->render($view, $data + [
            'basePath' => $this->base(),
            'csrfToken' => Session::csrfToken(),
        ]);
    }

    private function go(string $path): void
    {
        header(
            'Location: ' . $this->base() . $path,
            true,
            302
        );
    }

    private function user(): ?array
    {
        return Session::userId() === null
            ? null
            : Auth::user();
    }

    private function web(callable $work): void
    {
        try {
            $work();
        } catch (ApiException $error) {
            if ($error->status === 401) {
                $this->go('/login?next=guest');
                return;
            }

            http_response_code($error->status);

            $messages = [
                'ACCOUNT_LOCKED' => 'Tài khoản đã bị khóa.',
                'ACCOUNT_DEACTIVATED' => 'Tài khoản hiện không hoạt động.',
            ];

            $this->page('learning/index', [
                'loadError' => $messages[$error->errorCode]
                    ?? $error->getMessage(),
                'isGuest' => Session::userId() === null,
            ]);
        } catch (\Throwable $error) {
            $ref = 'LEARN-' . strtoupper(bin2hex(random_bytes(6)));

            error_log('[' . $ref . '] ' . (string) $error);
            http_response_code(500);

            $this->page('learning/index', [
                'loadError' => 'Chưa tải được dữ liệu học.',
                'errorReference' => $ref,
                'isGuest' => Session::userId() === null,
            ]);
        }
    }

    private function pathData(?array $user): array
    {
        if ($user !== null) {
            return (new LearningService($this->repository()))
                ->getLearningPathData($user)
                + ['isGuest' => false];
        }

        $service = $this->guest();
        $catalogue = $service->catalogue();

        $firstId = (int) ($catalogue['first']['id'] ?? 0);

        $ready = $firstId > 0
            ? $service->lessonPage($firstId)['ready']
            : false;

        $groups = $catalogue['groups'];

        foreach ($groups as &$lessons) {
            foreach ($lessons as &$lesson) {
                $first = (int) $lesson['id'] === $firstId;

                $lesson['unlocked'] = $first;
                $lesson['ready'] = $first && $ready;
                $lesson['completed'] = false;
            }

            unset($lesson);
        }

        unset($lessons);

        return [
            'topics' => $catalogue['topics'],
            'lessonsByTopic' => $groups,
            'firstLessonId' => $firstId,
            'isGuest' => true,
            'guestCompleted' => $service->pending(),
        ];
    }

    private function validFocus(mixed $focus): string
    {
        if (
            !is_string($focus)
            || !in_array($focus, ['words', 'practice'], true)
        ) {
            throw new ApiException(
                'VALIDATION_FAILED',
                'Chế độ không hợp lệ.',
                422
            );
        }

        return $focus;
    }

    private function entryFocus(): string
    {
        Session::start();

        $focus = $_SESSION[self::ENTRY_KEY] ?? 'practice';

        return in_array($focus, ['words', 'practice'], true)
            ? $focus
            : 'practice';
    }

    private function completed(int $lessonId, ?array $user): bool
    {
        if ($user !== null) {
            return in_array(
                $lessonId,
                $this->repository()->completedLessons((int) $user['id']),
                true
            );
        }

        $state = $this->guest()->state();

        return $state !== null
            && $state['status'] === 'completed'
            && (int) $state['lesson_id'] === $lessonId;
    }

    public function index(array $params = []): void
    {
        $this->web(function (): void {
            $user = $this->user();

            if ($user !== null && $this->guest()->pending()) {
                $this->go('/learning/continue');
                return;
            }

            $this->page(
                'learning/index',
                $this->pathData($user)
            );
        });
    }

    public function home(array $params = []): void
    {
        $this->web(function (): void {
            $repo = $this->repository();
            $lessons = $repo->getActiveLessons();
            $topics = $repo->getActiveTopics();
            $heroWords = [];
            foreach ($topics as &$topic) {
                $words = $repo->getWordsByTopicId((int) $topic['id']);
                $topic['word_count'] = count($words);
                $topic['lesson_count'] = count(array_filter($lessons,
                    static fn(array $lesson): bool => (int) $lesson['topic_id'] === (int) $topic['id']));
                $topic['cover'] = null;
                foreach ($words as $word) {
                    $topic['cover'] ??= \App\Modules\Learning\Services\ExerciseService::media($word['image'] ?? null);
                    if (count($heroWords) < 2) { $heroWords[] = $word; }
                }
            }
            unset($topic);
            $this->page('home', ['topicsHome' => $topics, 'heroWords' => $heroWords,
                'isGuest' => $this->user() === null]);
        });
    }

    public function progress(array $params = []): void
    {
        $this->web(function (): void {
            $this->page(
                'learning/index',
                $this->pathData($this->user())
                    + ['screen' => 'progress']
            );
        });
    }

    public function launch(array $params = []): void
    {
        $this->web(function (): void {
            $mode = $this->validFocus($_GET['mode'] ?? 'words');
            $user = $this->user();

            Session::start();
            $_SESSION[self::ENTRY_KEY] = $mode;

            if ($user !== null && $this->guest()->pending()) {
                $this->go('/learning/continue');
                return;
            }

            $data = $this->pathData($user);

            $chosen = null;
            $fallback = null;

            foreach ($data['lessonsByTopic'] as $lessons) {
                foreach ($lessons as $lesson) {
                    if (empty($lesson['unlocked'])) {
                        continue;
                    }

                    $fallback ??= $lesson;

                    if (empty($lesson['completed'])) {
                        $chosen = $lesson;
                        break 2;
                    }
                }
            }

            if (
                $chosen === null
                && $mode === 'practice'
                && $fallback !== null
            ) {
                unset($_SESSION[self::ENTRY_KEY]);

                $this->go('/learning/progress');
                return;
            }

            $chosen ??= $fallback;

            if ($chosen === null) {
                unset($_SESSION[self::ENTRY_KEY]);

                $this->go('/learning');
                return;
            }

            $this->go(
                '/learning/lesson/'
                . (int) $chosen['id']
                . '?focus=' . $mode
            );
        });
    }

    public function lesson(array $params): void
    {
        $this->web(function () use ($params): void {
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

            $user = $this->user();
            $focus = $_GET['focus'] ?? null;

            if (
                $focus !== null
                && (
                    !is_string($focus)
                    || !in_array(
                        $focus,
                        ['words', 'practice', 'result'],
                        true
                    )
                )
            ) {
                throw new ApiException(
                    'VALIDATION_FAILED',
                    'Chế độ không hợp lệ.',
                    422
                );
            }

            // Chỉ vào luồng làm lại khi người dùng chủ động chọn.
            $retry = $focus === 'practice'
                && ($_GET['retry'] ?? null) === '1';

            // Bài đã hoàn thành mặc định mở kết quả đã lưu.
            if (
                $focus === 'result'
                || (
                    $focus !== 'words'
                    && !$retry
                    && $this->completed($id, $user)
                    && ($user===null || $this->repository()->activeSession((int)$user['id'],(int)$id)===null)
                )
            ) {
                unset($_SESSION[self::ENTRY_KEY]);

                (new LearningResultController())->show([
                    'id' => $id,
                ]);

                return;
            }

            if ($user !== null && $this->guest()->pending()) {
                $this->go('/learning/continue');
                return;
            }

            $data = $user === null
                ? $this->guest()->lessonPage($id)
                : (new LearningService($this->repository()))
                    ->getLessonFlashcards(
                        $id,
                        (int) $user['id']
                    );

            if ($focus === null) {
                $this->go(
                    '/learning/lesson/'
                    . $id
                    . '?focus=' . $this->entryFocus()
                );
                return;
            }

            $this->validFocus($focus);
            unset($_SESSION[self::ENTRY_KEY]);

            $topicName = '';

            foreach ($this->repository()->getActiveTopics() as $topic) {
                if (
                    (int) $topic['id']
                    === (int) $data['lesson']['topic_id']
                ) {
                    $topicName = (string) $topic['name'];
                    break;
                }
            }

            $name = (string) $data['lesson']['name'];

            if (
                $topicName !== ''
                && !str_starts_with($name, $topicName)
            ) {
                $data['lesson']['name'] = $topicName . ' — ' . $name;
            }

            $data['isGuest'] = $user === null;
            $data['mode'] = 'lesson';

            $this->page(
                $user === null
                    ? 'learning/guest'
                    : 'learning/flashcard',
                $data
            );
        });
    }

    public function resume(array $params = []): void
    {
        $this->web(function (): void {
            Auth::user();

            $focus = $_GET['focus'] ?? null;

            if ($focus === null) {
                $this->go(
                    '/learning/continue?focus=' . $this->entryFocus()
                );
                return;
            }

            $this->validFocus($focus);

            $this->page(
                'learning/guest',
                ['mode' => 'continue']
            );
        });
    }

    private function onlyGuest(): void
    {
        if (Session::userId() !== null) {
            Auth::user();

            throw new ApiException(
                'TRIAL_SIGNED_IN',
                'Bạn đã đăng nhập.',
                409
            );
        }
    }

    public function start(array $params = []): void
    {
        $this->onlyGuest();
        Session::verifyCsrf();

        $body = Request::json();

        Response::data(
            $this->guest()->start(
                ($body['restart'] ?? false) === true
            )
        );
    }

    public function state(array $params = []): void
    {
        $this->onlyGuest();

        Response::data(
            $this->guest()->state()
        );
    }

    public function answer(array $params = []): void
    {
        $this->onlyGuest();
        Session::verifyCsrf();

        Response::data(
            $this->guest()->answer(Request::json())
        );
    }

    public function claim(array $params = []): void
    {
        $user = Auth::user();
        Session::verifyCsrf();

        Response::data(
            $this->guest()->claim((int) $user['id'])
        );
    }
}
