<?php
declare(strict_types=1);

namespace FlujosDimension\Controllers;

use FlujosDimension\Services\JwtKeyService;
use Throwable;

class JwtController
{
    private JwtKeyService $svc;
    private string $basePath;
    private string $adminPath;

    public function __construct(?string $basePath = null, ?string $adminPath = null)
    {
        // Evitamos depender de la constante BASE_PATH (que es global) dentro del namespace.
        // Tomamos la raíz relativa al archivo. Si quieres soportar BASE_PATH, descomenta el bloque inferior.
        $defaultBase      = $basePath ?? \dirname(__DIR__, 2);
        $this->basePath   = $defaultBase;
        $this->adminPath  = $adminPath ?? ($defaultBase . '/admin');
        $this->svc        = new JwtKeyService($defaultBase);

        /*
        // Alternativa si quieres soportar BASE_PATH además de fallback:
        $globalBase       = \defined('BASE_PATH') ? \constant('BASE_PATH') : \dirname(__DIR__, 2);
        $this->basePath   = $basePath ?? $globalBase;
        $this->adminPath  = $adminPath ?? ($this->basePath . '/admin');
        $this->svc        = new JwtKeyService($this->basePath);
        */
    }

    public function index(): void
    {
        $paths = [
            'jwks' => $this->basePath . '/storage/keys/jwks.json',
            'priv' => $this->basePath . '/storage/keys/private',
            'pub'  => $this->basePath . '/storage/keys/public',
        ];

        $envPath = $this->basePath . '/.env';
        $env     = @parse_ini_file($envPath, false, \INI_SCANNER_RAW) ?: [];
        $vars = [
            'JWT_ALG'              => $env['JWT_ALG'] ?? 'HS256',
            'JWT_KID'              => $env['JWT_KID'] ?? null,
            'JWT_PRIVATE_KEY_PATH' => $env['JWT_PRIVATE_KEY_PATH'] ?? null,
            'JWT_JWKS_PATH'        => $env['JWT_JWKS_PATH'] ?? null,
        ];

        if (\session_status() !== \PHP_SESSION_ACTIVE) { \session_start(); }
        $success = $_SESSION['flash_success'] ?? null;
        $error   = $_SESSION['flash_error']   ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $jwks       = $this->svc->jwks();
        $currentKid = $vars['JWT_KID'];

        include $this->adminPath . '/views/jwt_manager.php';
    }

    public function generate(): void
    {
        if (\session_status() !== \PHP_SESSION_ACTIVE) { \session_start(); }
        try {
            $out = $this->svc->generate();
            $_SESSION['flash_success'] = "Clave creada y activada (KID {$out['kid']}).";
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Error generando par RSA: ' . $e->getMessage();
        }
        \header('Location: /admin/?action=jwt'); exit;
    }

    public function importPem(): void
    {
        if (\session_status() !== \PHP_SESSION_ACTIVE) { \session_start(); }
        try {
            if (!isset($_FILES['private_pem']) || $_FILES['private_pem']['error'] !== \UPLOAD_ERR_OK) {
                throw new \RuntimeException('Sube el private.pem');
            }
            $priv = $_FILES['private_pem']['tmp_name'];
            $pub  = (isset($_FILES['public_pem']) && $_FILES['public_pem']['error'] === \UPLOAD_ERR_OK)
                ? $_FILES['public_pem']['tmp_name']
                : null;

            $out  = $this->svc->import($priv, $pub);
            $_SESSION['flash_success'] = "Importado y activado (KID {$out['kid']}).";
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Error importando PEM: ' . $e->getMessage();
        }
        \header('Location: /admin/?action=jwt'); exit;
    }

    public function rotate(): void
    {
        if (\session_status() !== \PHP_SESSION_ACTIVE) { \session_start(); }
        try {
            $kid = $_POST['kid'] ?? '';
            if (!$kid) { throw new \RuntimeException('KID requerido'); }
            $this->svc->rotate($kid);
            $_SESSION['flash_success'] = "KID activo ahora: {$kid}";
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Error rotando KID: ' . $e->getMessage();
        }
        \header('Location: /admin/?action=jwt'); exit;
    }

    public function serveJwks(): void
    {
        \header('Content-Type: application/json');
        \header('Cache-Control: public, max-age=300');
        \header('Access-Control-Allow-Origin: *');
        echo \json_encode($this->svc->jwks(), \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT);
    }
}
