<?php
declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\Auth;
use App\Modules\Users\Repositories\UserRepository;
use App\Modules\Users\Services\UserService;

final class UsersController
{
    private function service(): UserService
    {
        return new UserService(new UserRepository(Database::connection()));
    }

    public function me(array $params): void
    {
        header('Cache-Control: no-store');
        Response::data(UserService::present(Auth::user()));
    }

    public function updateMe(array $params): void
    {
        $user = Auth::user();
        Response::data($this->service()->updateProfile((int) $user['id'], Request::json()));
    }

    public function updateStatus(array $params): void
    {

        $admin = Auth::admin();
        \App\Core\Session::verifyCsrf();
        Response::data($this->service()->setStatus($admin, (string) ($params['id'] ?? ''), Request::json()));
    }
}