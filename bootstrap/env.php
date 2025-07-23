<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);
$envFile  = $rootPath . '/.env';

if (file_exists($envFile)) {
    $dotenv = Dotenv::createImmutable($rootPath);
    $dotenv->safeLoad();
}
