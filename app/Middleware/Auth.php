<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\Session;
use App\Modules\Users\Repositories\UserRepository;

final class Auth
{
    public static function user(): array
    {
        $id = Session::userId();
        if ($id === null) {
            throw new ApiException('UNAUTHENTICATED', 'Authentication required', 401);
        }

        $user = (new UserRepository(Database::connection()))->findById($id);
        if ($user === null) {
            Session::destroy();
            throw new ApiException('UNAUTHENTICATED', 'Authentication required', 401);
        }
        if ($user['status'] === 'locked') {
            Session::destroy();
            throw new ApiException('ACCOUNT_LOCKED', 'Account is locked', 403);
        }
        if ($user['status'] === 'deactivated') {
            Session::destroy();
            throw new ApiException('ACCOUNT_DEACTIVATED', 'Account is deactivated', 403);
        }


        return $user;
    }

    public static function admin(): array
    {
        $user = self::user();
        if ($user['role'] !== 'admin') {
            throw new ApiException('FORBIDDEN', 'Insufficient permissions', 403);
        }

        return $user;
    }
}