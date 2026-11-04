<?php
declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Users\Repositories\UserRepository;

final class AuthController
{
    private function service(): AuthService
    {
        return new AuthService(new UserRepository(Database::connection()));
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
        Response::data($user);
    }


    public function logout(array $params): void
    {
        if (Session::userId() === null) {
            throw new ApiException('UNAUTHENTICATED', 'Authentication required', 401);
        }
        Session::destroy();
        Response::data(['logged_out' => true]);
    }
}