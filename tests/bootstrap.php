<?php
require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);

// Cargar .env.testing y .env de forma segura (si usas vlucas/phpdotenv)
if (class_exists(Dotenv\Dotenv::class)) {
    $files = [];
    if (is_file($root.'/.env.testing')) { $files[] = '.env.testing'; }
    if (is_file($root.'/.env'))         { $files[] = '.env'; }
    if ($files) {
        Dotenv\Dotenv::createImmutable($root, $files)->safeLoad();
    }
}

// Forzar modo testing
putenv('APP_ENV=testing'); $_ENV['APP_ENV']='testing'; $_SERVER['APP_ENV']='testing';

// Asegurar BASE_PATH para código que dependa de ello
if (!getenv('BASE_PATH')) {
    putenv("BASE_PATH={$root}");
    $_ENV['BASE_PATH'] = $root;
    $_SERVER['BASE_PATH'] = $root;
}

// (Opcional) Si tu código usa constantes:
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $root);
}
