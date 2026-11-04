<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            // Gắn cứng thông số kết nối XAMPP để chạy ngay lập tức
            $host = '127.0.0.1';
            $port = 3306;
            $database = 'doulingo';
            $username = 'root'; 
            $password = '';
            $charset = 'utf8mb4';

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $host,
                $port,
                $database,
                $charset
            );

            try {
                self::$connection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException('Khong the ket noi co so du lieu: ' . $e->getMessage());
            }
        }

        return self::$connection;
    }
}