<?php
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap/env.php';
$error = '';

if (isset($_GET['action']) && $_GET['action'] === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF inválido';
    } else {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';
        $envUser = $_ENV['ADMIN_USER'] ?? 'admin';
        $envPassHash = $_ENV['ADMIN_PASS'] ?? password_hash('password', PASSWORD_DEFAULT);
        if ($user === $envUser && password_verify($pass, $envPassHash)) {
            $_SESSION['authenticated'] = true;
            $_SESSION['admin_user'] = $user;
            $_SESSION['login_time'] = time();
            header('Location: index.php');
            exit;
        } else {
            $error = 'Credenciales inválidas';
        }
    }
}

$csrf = csrfToken();
include __DIR__ . '/views/login.php';
