<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
requireApiAuth();

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

if (!isset($container)) {
    $container = require dirname(__DIR__, 2) . '/app/bootstrap/container.php';
}

if (!defined('FD_TESTING')) {
    define('FD_TESTING', false);
}

use FlujosDimension\Core\Request;

$request = new Request();
header('Content-Type: application/json');

if (!function_exists('sanitize_string')) {
    function sanitize_string(string $value): string {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('post_bool')) {
    function post_bool(Request $request, string $key, bool $default = false): bool {
        $val = $request->post($key);
        return $val === null ? $default : (bool)filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('respond_error')) {
    function respond_error(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message]);
        if (FD_TESTING) {
            throw new \RuntimeException($message);
        }
        exit;
    }
}

if (!function_exists('validate_fields')) {
    function validate_fields(Request $request, array $fields): void {
        try {
            $request->validate($fields);
        } catch (InvalidArgumentException $e) {
            respond_error($e->getMessage(), 400);
        }
    }
}

if (!function_exists('validate_input')) {
    /**
     * Validate POST parameters using filter_var rules.
     *
     * @param array<string, array{filter:int, required?:bool}> $rules
     * @return array<string, mixed>
     */
    function validate_input(Request $request, array $rules): array {
        $data = [];
        foreach ($rules as $name => $opts) {
            $required = $opts['required'] ?? false;
            $filter   = $opts['filter']   ?? FILTER_DEFAULT;
            $value    = $request->post($name);

            if ($value === null) {
                if ($required) {
                    respond_error("Missing field: $name", 400);
                }
                continue;
            }

            $filtered = filter_var($value, $filter, ['flags' => FILTER_NULL_ON_FAILURE]);
            if ($filtered === null && $filter !== FILTER_DEFAULT) {
                respond_error("Invalid value for $name", 400);
            }
            $data[$name] = $filtered ?? $value;
        }

        return $data;
    }
}
