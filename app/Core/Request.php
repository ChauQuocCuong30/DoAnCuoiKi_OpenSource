<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new ApiException('VALIDATION_FAILED', 'Request body must be a JSON object', 422);
        }

        return $data;
    }
}