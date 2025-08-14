<?php
declare(strict_types=1);

namespace FlujosDimension\Controllers\Admin;

use FlujosDimension\Services\SyncService;

class SyncController
{
    private string $basePath;
    private string $adminPath;
    private SyncService $svc;

    public function __construct(?string $basePath = null, ?string $adminPath = null)
    {
        $this->basePath  = $basePath  ?? dirname(__DIR__, 3);
        $this->adminPath = $adminPath ?? ($this->basePath . '/admin');
        $container       = require $this->basePath . '/app/bootstrap/container.php';
        $this->svc       = $container->resolve(SyncService::class);
    }

    public function index(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $success = $_SESSION['flash_success'] ?? null;
        $error   = $_SESSION['flash_error']   ?? null;
        $result  = $_SESSION['sync_result']   ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['sync_result']);
        include $this->adminPath . '/views/sync.php';
    }

    public function run(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $job = $_POST['job'] ?? '';
        $params = $_POST;
        unset($params['job']);
        try {
            switch ($job) {
                case 'ringover':
                    $res = $this->svc->runRingover($params);
                    break;
                case 'openai':
                    $res = $this->svc->runBatchOpenAI($params);
                    break;
                case 'pipedrive':
                    $res = $this->svc->pushPipedrive($params);
                    break;
                default:
                    throw new \InvalidArgumentException('Job invalido');
            }
            $_SESSION['sync_result'] = $res;
            if ($res['ok']) {
                $_SESSION['flash_success'] = 'Ejecuci\u00f3n completada.';
            } else {
                $_SESSION['flash_error'] = 'Ejecuci\u00f3n con errores.';
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Excepci\u00f3n: ' . $e->getMessage();
        }
        header('Location: /admin/?action=sync');
        exit;
    }
}
