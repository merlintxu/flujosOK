<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use FlujosDimension\Core\Request;

function isAuthenticated(): bool {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function requireLogin(): void {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(?string $token): bool {
    return $token !== null && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function requireApiAuth(): void {
    $request = new Request();

    if (!isAuthenticated()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    if ($request->isMethod('POST')) {
        $token = $request->post('csrf_token') ?? $request->getHeader('x-csrf-token') ?? '';
        if (!verifyCsrf($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
    }
}

function logout(): void {
    session_unset();
    session_destroy();
}
