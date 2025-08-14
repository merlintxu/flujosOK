<?php
declare(strict_types=1);

/**
 * Panel de Administración — Front Controller
 *
 * - PSR-4: sólo vendor/autoload.php (sin require_once a rutas físicas).
 * - Rutas coherentes: dashboard, env/env_editor, system_health, api_management, api_test.
 * - Gestor JWS/JWT integrado: jwt, jwt.generate, jwt.import, jwt.rotate, jwt.jwks.
 * - Inyección de variables por defecto en vistas (ej.: $callStats) para evitar warnings.
 */

ini_set('display_errors', '0');      // Producción: no mostrar errores
ini_set('log_errors', '1');
error_reporting(E_ALL);

// -----------------------------------------------------------------------------
// Autoload y paths base
// -----------------------------------------------------------------------------
$PROJECT_ROOT = dirname(__DIR__);
$VENDOR_AUTO  = $PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists($VENDOR_AUTO)) {
    http_response_code(500);
    echo "Falta vendor/autoload.php. Ejecuta 'composer install'.";
    exit;
}
require_once $VENDOR_AUTO;

// (Opcional) Cargar .env si está vlucas/phpdotenv
if (class_exists(\Dotenv\Dotenv::class)) {
    try { \Dotenv\Dotenv::createImmutable($PROJECT_ROOT)->safeLoad(); } catch (\Throwable $e) {}
}

// Sesión
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Guardia de autenticación (si existe en tu proyecto)
if (function_exists('requireLogin')) { requireLogin(); }

// -----------------------------------------------------------------------------
// Imports PSR-4
// -----------------------------------------------------------------------------
use FlujosDimension\Controllers\JwtController;

// Instancia del Gestor JWT (rutas base para vistas/keys)
$jwt = new JwtController($PROJECT_ROOT, __DIR__);

// -----------------------------------------------------------------------------
// Helpers de render y utilidades
// -----------------------------------------------------------------------------
/** Renderiza una vista PHP simple. */
function render_view(string $viewPath, array $vars = []): void {
    extract($vars, EXTR_SKIP);
    if (file_exists($viewPath)) {
        include $viewPath;
    } else {
        echo "<!doctype html><meta charset='utf-8'><title>Vista no encontrada</title>";
        echo "<h1>Vista no encontrada</h1><p>" . htmlspecialchars($viewPath, ENT_QUOTES) . "</p>";
    }
}

/** 405 Method Not Allowed */
function method_not_allowed(): void {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Método no permitido";
}

/** Log de errores del panel (si existe tu Logger PSR-3). */
function admin_log_error(\Throwable $e): void {
    if (class_exists(\FlujosDimension\Core\Logger::class)) {
        try {
            $logDir = dirname(__DIR__) . '/storage/logs';
            $logger = new \FlujosDimension\Core\Logger($logDir, \Psr\Log\LogLevel::ERROR);
            $logger->error('Admin error', ['exception' => $e]);
        } catch (\Throwable $ignored) {}
    }
}

/** Variables seguras por defecto para dashboard (evita Undefined variable/index). */
function prepare_dashboard_vars(): array {
    $defaults = [
        'callStats' => [
            'total'  => 0, 'today' => 0, 'week' => 0, 'month' => 0, 'errors' => 0,
            'ringover'  => ['today' => 0, 'month' => 0, 'errors' => 0],
            'openai'    => ['requests_today' => 0, 'cost_today' => 0.0],
            'pipedrive' => ['api_calls_today' => 0],
        ],
        // añade aquí otras variables que tu dashboard espere
    ];
    // Opcional: merge con cache si existe
    $cache = dirname(__DIR__) . '/storage/cache/call_stats.json';
    if (is_file($cache)) {
        $data = json_decode((string)file_get_contents($cache), true);
        if (is_array($data)) {
            $defaults['callStats'] = array_replace_recursive($defaults['callStats'], $data);
        }
    }
    return $defaults;
}

/** Variables para env_editor: prepara mensajes flash si la vista los usa. */
function prepare_env_vars(): array {
    $success = $_SESSION['flash_success'] ?? null;
    $error   = $_SESSION['flash_error']   ?? null;
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    return compact('success','error');
}

// -----------------------------------------------------------------------------
// Router ?action=...
// -----------------------------------------------------------------------------
$action = $_GET['action'] ?? 'dashboard';

try {
    switch ($action) {

        // ------------------------- Dashboard -------------------------
        case 'dashboard': {
            $dashboardView = __DIR__ . '/views/dashboard.php';
            $vars = prepare_dashboard_vars();
            // Pasa también flags para mostrar botones OpenAI/Pipedrive
            $vars += ['showOpenAI' => true, 'showPipedrive' => true, 'showJwtBtn' => true];
            render_view($dashboardView, $vars);
            break;
        }

        // -------------------- Variables de Entorno --------------------
        case 'env':
        case 'env_editor': {
            $envView = __DIR__ . '/views/env_editor.php';
            render_view($envView, prepare_env_vars());
            break;
        }

        // --------------------- Salud del Sistema ----------------------
        case 'system_health': {
            $view = __DIR__ . '/views/system_health.php';
            // inyecta defaults si esa vista espera algo concreto
            render_view($view, []);
            break;
        }

        // ---------------------- Gestión API (Keys) --------------------
        case 'api_management': {
            $view = __DIR__ . '/views/api_management.php';
            render_view($view, []);
            break;
        }

        // -------------------------- Test APIs -------------------------
        case 'api_test': {
            $view = __DIR__ . '/views/api_test.php';
            render_view($view, []);
            break;
        }

        // ------------------------- Gestor JWT -------------------------
        case 'jwt': {               // UI
            $jwt->index();
            break;
        }
        case 'jwt.generate': {      // POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { method_not_allowed(); break; }
            $jwt->generate();   // redirige a ?action=jwt
            break;
        }
        case 'jwt.import': {        // POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { method_not_allowed(); break; }
            $jwt->importPem();  // redirige a ?action=jwt
            break;
        }
        case 'jwt.rotate': {        // POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { method_not_allowed(); break; }
            $jwt->rotate();     // redirige a ?action=jwt
            break;
        }
        case 'jwt.jwks': {          // GET
            $jwt->serveJwks();
            break;
        }

        // ---------------------------- 404 -----------------------------
        default: {
            http_response_code(404);
            echo "<!doctype html><meta charset='utf-8'><title>404</title>";
            echo "<h1>404 - Acción no encontrada</h1>";
            echo "<p>Acción: <code>" . htmlspecialchars($action, ENT_QUOTES) . "</code></p>";
            echo "<p><a href='/admin/?action=dashboard'>&larr; Volver al panel</a></p>";
            break;
        }
    }

} catch (\Throwable $e) {
    admin_log_error($e);
    http_response_code(500);
    $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES);
    echo "<!doctype html><meta charset='utf-8'><title>Error</title>";
    echo "<h1>Error en Admin</h1>";
    echo "<pre>{$msg}</pre>";
}
