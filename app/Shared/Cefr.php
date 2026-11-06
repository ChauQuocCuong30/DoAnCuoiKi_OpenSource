<?php
declare(strict_types=1);
namespace App\Shared;
use PDO;
use PDOException;
final class Cefr
{
    public const LEVELS = ['A1','A2','B1','B2','C1','C2'];
    public static function installed(PDO $db): bool
    {
        try { $db->query('SELECT cefr_level FROM words LIMIT 1'); return true; }
        catch (PDOException $e) {
            if (in_array($e->getCode(),['42S22','42S02'],true) || ($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite' && preg_match('/no such (column|table)/',$e->getMessage()))) { return false; }
            throw $e;
        }
    }
}
