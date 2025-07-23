<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/auth.php';

use FlujosDimension\Core\Config;
$config = Config::getInstance();


$error = '';

if (isset($_GET['action']) && $_GET['action'] === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF inválido';
    } else {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';
        $envUser = $config->get('ADMIN_USER', 'admin');
        $envPass = $config->get('ADMIN_PASS', 'password');
        if ($user === $envUser && $pass === $envPass) {
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
