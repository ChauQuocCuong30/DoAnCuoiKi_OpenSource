<?php
declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Learning\Repositories\GuestClaimRepository;
use App\Modules\Learning\Repositories\LearningRepository;
use App\Modules\Learning\Services\GuestLearningService;
use App\Modules\Users\Repositories\UserRepository;

final class AuthController
{
    private function service(): AuthService
    {
        return new AuthService(
            new UserRepository(Database::connection())
        );
    }

    public function register(array $params): void
    {
        $user = $this->service()->register(Request::json());

        Response::data($user, 201);
    }

    public function login(array $params): void
    {
        $user = $this->service()->login(Request::json());

        Session::login($user['id']);

        if ($user['role'] === 'user') {
            (new \App\Modules\Auth\Services\OnboardingService(Database::connection()))->attachDraft((int)$user['id']);
            $fresh=(new UserRepository(Database::connection()))->findById((int)$user['id']);
            if($fresh!==null)$user=array_replace($user,\App\Modules\Users\Services\UserService::present($fresh));
        }

        Response::data($user);
    }

    public function logout(array $params): void
    {
        $userId = Session::userId();

        if ($userId === null) {
            throw new ApiException(
                'UNAUTHENTICATED',
                'Authentication required',
                401
            );
        }

        $firstLessonId = null;

        try {
            $db = Database::connection();

            $repository = new LearningRepository($db);

            $guest = new GuestLearningService(
                $repository,
                new GuestClaimRepository($db)
            );

            $first = $guest->firstLesson();

            if (
                $first !== null
                && in_array(
                    (int) $first['id'],
                    $repository->completedLessons($userId),
                    true
                )
            ) {
                $firstLessonId = (int) $first['id'];
            }
        } catch (\Throwable $error) {
            error_log(
                'Unable to read the first-lesson preview during logout.'
            );
        }

        // Xóa đăng nhập, CSRF và dữ liệu học riêng trong phiên cũ.
        $_SESSION = [];

        if (session_regenerate_id(true)) {
            if ($firstLessonId !== null) {
                // Chỉ phục vụ hiển thị, không chứa tài khoản hoặc kết quả để claim.
                $_SESSION['learning_guest_preview_v1'] = [
                    'first_lesson_id' => $firstLessonId,
                ];
            }
        } else {
            Session::destroy();
        }

        Response::data([
            'logged_out' => true,
        ]);
    }
}
