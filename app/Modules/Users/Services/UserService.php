<?php
declare(strict_types=1);

namespace App\Modules\Users\Services;

use App\Core\ApiException;
use App\Modules\Users\Repositories\UserRepository;
use RuntimeException;

final class UserService
{
    private const EDITABLE = ['name', 'daily_goal_xp', 'streak_goal_days', 'timezone'];

    public function __construct(private UserRepository $users)
    {
    }

    public static function present(array $user): array
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


    public function updateProfile(int $userId, array $input): array
    {
        $errors = [];

        if ($input === []) {
            throw new ApiException('VALIDATION_FAILED', 'Validation failed', 422, ['body' => 'No fields to update']);
        }
        foreach (array_keys($input) as $key) {
            if (!in_array($key, self::EDITABLE, true)) {
                $errors[(string) $key] = 'Field is not allowed';
            }
        }

        if (array_key_exists('name', $input)) {
            $name = $input['name'];
            if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 100) {
                $errors['name'] = 'Name must be 1 to 100 characters';
            } else {
                $input['name'] = trim($name);
            }
        }
        if (array_key_exists('daily_goal_xp', $input)
            && (!is_int($input['daily_goal_xp']) || $input['daily_goal_xp'] < 1 || $input['daily_goal_xp'] > 1000)) {
            $errors['daily_goal_xp'] = 'Must be an integer from 1 to 1000';
        }
        if (array_key_exists('streak_goal_days', $input)
            && (!is_int($input['streak_goal_days']) || $input['streak_goal_days'] < 1 || $input['streak_goal_days'] > 365)) {
            $errors['streak_goal_days'] = 'Must be an integer from 1 to 365';
        }
        if (array_key_exists('timezone', $input)
            && (!is_string($input['timezone']) || !in_array($input['timezone'], timezone_identifiers_list(), true))) {
            $errors['timezone'] = 'Timezone is invalid';

        }

        if ($errors !== []) {
            throw new ApiException('VALIDATION_FAILED', 'Validation failed', 422, $errors);
        }

        $this->users->updateProfile($userId, $input);

        return self::present($this->requireUser($userId));
    }

    public function setStatus(array $admin, string $rawId, array $input): array
    {
        $status = $input['status'] ?? null;
        if (!is_string($status) || !in_array($status, ['locked', 'active'], true)) {
            throw new ApiException('VALIDATION_FAILED', 'Validation failed', 422, ['status' => 'Must be locked or active']);
        }
        if (!ctype_digit($rawId)) {
            throw new ApiException('USER_NOT_FOUND', 'User not found', 404);
        }

        $id = (int) $rawId;
        $target = $this->users->findById($id);
        if ($target === null) {
            throw new ApiException('USER_NOT_FOUND', 'User not found', 404);
        }
        if ($target['role'] !== 'user' || $id === (int) $admin['id']) {
            throw new ApiException('VALIDATION_FAILED', 'Validation failed', 422, ['id' => 'Admin cannot change own status']);
        }

        $this->users->updateStatus($id, $status);

        return self::present($this->requireUser($id));

    }

    private function requireUser(int $id): array
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new RuntimeException('User was not found');
        }

        return $user;
    }
}