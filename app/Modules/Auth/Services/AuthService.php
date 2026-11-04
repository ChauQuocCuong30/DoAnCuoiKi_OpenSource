<?php
declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\ApiException;
use App\Modules\Users\Repositories\UserRepository;
use PDOException;
use RuntimeException;

final class AuthService
{
    public function __construct(private UserRepository $users)
    {
    }

    public function register(array $input): array
    {
        $name = $input['name'] ?? null;
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;
        $errors = [];

        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 100) {
            $errors['name'] = 'Name must be 1 to 100 characters';
        }
        if (!is_string($email) || strlen(trim($email)) > 191 || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Email is invalid';
        }
        if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) {
            $errors['password'] = 'Password must be 8 to 72 characters';
        }

        if ($errors !== []) {
            throw new ApiException('VALIDATION_FAILED', 'Validation failed', 422, $errors);
        }

        $name = trim($name);
        $email = strtolower(trim($email));

        if ($this->users->findByEmail($email) !== null) {
            throw new ApiException('EMAIL_ALREADY_EXISTS', 'Email is already registered', 409);
        }

        try {
            $id = $this->users->create($name, $email, password_hash($password, PASSWORD_DEFAULT));
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new ApiException('EMAIL_ALREADY_EXISTS', 'Email is already registered', 409);
            }
            throw $e;
        }

        $user = $this->users->findById($id);
        if ($user === null) {
            throw new RuntimeException('User was not found after creation');
        }

        return $this->publicUser($user);
    }

    public function login(array $input): array
    {
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;

        $errors = [];

        if (!is_string($email) || trim($email) === '') {
            $errors['email'] = 'Email is required';
        }
        if (!is_string($password) || $password === '') {
            $errors['password'] = 'Password is required';
        }
        if ($errors !== []) {
            throw new ApiException('VALIDATION_FAILED', 'Validation failed', 422, $errors);
        }

        $user = $this->users->findByEmail(strtolower(trim($email)));
        // Luôn chạy password_verify để thời gian phản hồi không lộ email có tồn tại hay không.
        $hash = $user['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash);

        if ($user === null || !$valid) {
            throw new ApiException('INVALID_CREDENTIALS', 'Invalid email or password', 401);
        }
        if ($user['status'] === 'locked') {
            throw new ApiException('ACCOUNT_LOCKED', 'Account is locked', 403);
        }
        if ($user['status'] === 'deactivated') {
            throw new ApiException('ACCOUNT_DEACTIVATED', 'Account is deactivated', 403);
        }

        return $this->publicUser($user);
    }

    private function publicUser(array $user): array
    {

        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'current_hp' => (int) $user['current_hp'],
            'daily_goal_xp' => (int) $user['daily_goal_xp'],
            'streak_goal_days' => (int) $user['streak_goal_days'],
            'timezone' => $user['timezone'],
        ];
    }
}