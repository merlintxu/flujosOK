<?php
declare(strict_types=1);

use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);
$envFile  = $rootPath . '/.env';

if (file_exists($envFile)) {
    $dotenv = Dotenv::createImmutable($rootPath);
    $dotenv->safeLoad();
}
