<?php
declare(strict_types=1);

use App\Services\JwtKeyService;

class JwtController
{
    private JwtKeyService $svc;

    public function __construct()
    {
        // ajusta BASE_PATH a tu raíz del proyecto
        $this->svc = new JwtKeyService(BASE_PATH);
        // TODO: añade aquí tu chequeo de auth de admin (Basic/Auth propia)
    }

    public function index(): void
    {
        $jwks = $this->svc->getJwks();
        $currentKid = $this->svc->getCurrentKid();
        $paths = $this->svc->getPaths();

        $env = parse_ini_file(BASE_PATH.'/.env', false, INI_SCANNER_RAW) ?: [];
        $vars = [
            'JWT_ALG' => $env['JWT_ALG'] ?? null,
            'JWT_KID' => $env['JWT_KID'] ?? null,
            'JWT_PRIVATE_KEY_PATH' => $env['JWT_PRIVATE_KEY_PATH'] ?? null,
            'JWT_JWKS_PATH' => $env['JWT_JWKS_PATH'] ?? null,
        ];

        // mensajes flash
        session_start();
        $success = $_SESSION['flash_success'] ?? null;
        $error   = $_SESSION['flash_error']   ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        include __DIR__ . '/../views/jwt_manager.php';
    }

    public function generate(): void
    {
        session_start();
        try {
            $out = $this->svc->generateNewPair();
            $_SESSION['flash_success'] = "Clave creada y activada (KID {$out['kid']}). JWKS: {$out['jwks']}";
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Error generando par RSA: ' . $e->getMessage();
        }
        header('Location: /admin/?action=jwt'); exit;
    }

    public function importPem(): void
    {
        session_start();
        try {
            if (!isset($_FILES['private_pem']) || $_FILES['private_pem']['error']!==UPLOAD_ERR_OK) {
                throw new RuntimeException('Sube el private.pem');
            }
            $privTmp = $_FILES['private_pem']['tmp_name'];
            $pubTmp  = (isset($_FILES['public_pem']) && $_FILES['public_pem']['error']===UPLOAD_ERR_OK) ? $_FILES['public_pem']['tmp_name'] : null;
            $out = $this->svc->importFromPem($privTmp, $pubTmp);
            $_SESSION['flash_success'] = "Clave importada y activada (KID {$out['kid']}).";
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Error importando PEM: ' . $e->getMessage();
        }
        header('Location: /admin/?action=jwt'); exit;
    }

    public function rotate(): void
    {
        session_start();
        try {
            $kid = $_POST['kid'] ?? '';
            if (!$kid) throw new RuntimeException('KID requerido');
            $this->svc->rotateKid($kid);
            $_SESSION['flash_success'] = "KID activo ahora: {$kid}";
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Error rotando KID: ' . $e->getMessage();
        }
        header('Location: /admin/?action=jwt'); exit;
    }

    // Endpoint público: apúntalo detrás de auth inverso si quieres restringir
    public function serveJwks(): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: public, max-age=300');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($this->svc->getJwks(), JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    }
}
