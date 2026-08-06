<?php
/**
 * API Response helper
 * Standardized JSON response format used by all endpoints
 *
 * {
 *   "success": true|false,
 *   "message": "...",
 *   "data": {...},
 *   "errors": []
 * }
 */

class Response
{
    public static function send(bool $success, string $message = '', $data = null, array $errors = [], int $httpStatus = 200): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        http_response_code($httpStatus);

        $payload = [
            'success' => $success,
            'message' => $message,
            'data' => $data ?? new stdClass(),
            'errors' => $errors,
        ];

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(string $message = 'OK', $data = null, int $httpStatus = 200): void
    {
        self::send(true, $message, $data, [], $httpStatus);
    }

    public static function error(string $message = 'Error', array $errors = [], int $httpStatus = 400): void
    {
        self::send(false, $message, null, $errors, $httpStatus);
    }
}
