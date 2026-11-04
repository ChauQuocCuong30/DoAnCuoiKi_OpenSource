<?php
declare(strict_types=1);

namespace App\Modules\Users\Repositories;

use PDO;

final class UserRepository
{
    private const COLUMNS = 'id, name, email, password_hash, role, status, current_hp, daily_goal_xp, streak_goal_days, timezone';
    private const PROFILE_COLUMNS = ['name', 'daily_goal_xp', 'streak_goal_days', 'timezone'];

    public function __construct(private PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;

    }

    public function create(string $name, string $email, string $passwordHash): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)'
        );
        $stmt->execute(['name' => $name, 'email' => $email, 'password_hash' => $passwordHash]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateProfile(int $id, array $fields): void
    {
        $sets = [];
        $params = ['id' => $id];
        foreach (self::PROFILE_COLUMNS as $column) {
            if (array_key_exists($column, $fields)) {
                $sets[] = $column . ' = :' . $column;
                $params[$column] = $fields[$column];
            }
        }
        if ($sets === []) {
            return;
        }

        $stmt = $this->pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    public function updateStatus(int $id, string $status): void
    {

        $stmt = $this->pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }
}