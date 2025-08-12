<?php

declare(strict_types=1);

namespace FlujosDimension\Core;

use FlujosDimension\Core\{Config,Database,JWT,CacheManager};
use PDO;
/**
 * Aplicación Principal - Flujos Dimension v4.2
 * Migrado y mejorado desde v3
 */
class Application
{
    private Container $container;
    private Router $router;
    private Request $request;
    private ErrorHandler $errorHandler;
    private Config $config;
    private ?PDO $database = null;
    
    public function __construct()
    {
        $this->loadConfiguration();
        $this->initializeCore();
        $this->registerServices();
        $this->setupErrorHandling();
        $this->ensureStorageDirectories();
    }
    
    /**
     * Cargar configuración desde .env
     */
    private function loadConfiguration(): void
    {
        // Cargar variables de entorno desde bootstrap
        require_once dirname(__DIR__, 2) . '/bootstrap/env.php';

        $this->config = Config::getInstance();

        // Valores por defecto
        $defaults = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'TIMEZONE' => 'Europe/Madrid',
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
            'JWT_EXPIRATION_HOURS' => '24'
        ];

        foreach ($defaults as $key => $value) {
            if (!$this->config->has($key)) {
                $this->config->set($key, $value);
            }
        }

        if (!$this->config->validateRequiredConfig()) {
            throw new \RuntimeException('Missing required configuration values');
        }

        // Configurar zona horaria
        date_default_timezone_set($this->config->get('TIMEZONE'));

    }
    
    /**
     * Inicializar componentes core
     */
    private function initializeCore(): void
    {
        $this->container = new Container();
        $this->request = new Request();
        $this->router = new Router($this->container);
        $this->errorHandler = new ErrorHandler($this->config->get('APP_DEBUG') === 'true');
    }
    
    /**
     * Registrar servicios en el contenedor
     */
/**
 * Registrar servicios y utilidades en el contenedor DI
 */

private function registerServices(): void
{
    /* ---------- Configuración y núcleo ---------- */
    $this->container->singleton(Config::class, fn () => $this->config);
    $this->container->alias(Config::class, 'config');

    // Simple file based cache
    $this->container->singleton(
        CacheManager::class,
        fn () => new CacheManager(dirname(__DIR__, 2) . '/storage/cache')
    );
    $this->container->alias(CacheManager::class, 'cache');

    // Conexión PDO única
    $this->container->singleton(PDO::class, fn () => $this->getDatabaseConnection());
    // Alias para acceder a la base de datos por nombre
    $this->container->alias(PDO::class, 'database');

    /* ---------- JWT ---------- */
    $this->container->singleton(JWT::class, fn ($c) => new JWT($c->resolve(PDO::class)));
    $this->container->alias(JWT::class, 'jwtService');

    /* ---------- Logger ---------- */
    $this->container->singleton('logger', fn () =>
        new \FlujosDimension\Core\Logger(dirname(__DIR__, 2) . '/storage/logs')
    );

    /* ---------- HttpClient (Guzzle + retry) ---------- */
    $this->container->singleton(
        \FlujosDimension\Infrastructure\Http\HttpClient::class,
        fn ($c) => new \FlujosDimension\Infrastructure\Http\HttpClient(
            [],
            5,
            500,
            $c->resolve(PDO::class),
            $c->resolve('logger')
        )
    );
    // Alias corto por si te resulta práctico
    $this->container->alias(
        \FlujosDimension\Infrastructure\Http\HttpClient::class,
        'httpClient'
    );

    /* ---------- Repositorios ---------- */
    $this->container->singleton(
        \FlujosDimension\Repositories\CallRepository::class,
        fn ($c) => new \FlujosDimension\Repositories\CallRepository(
            $c->resolve(PDO::class)
        )
    );
    $this->container->alias(
        \FlujosDimension\Repositories\CallRepository::class,
        'callRepository'
    );

    $this->container->singleton(
        \FlujosDimension\Repositories\SyncHistoryRepository::class,
        fn ($c) => new \FlujosDimension\Repositories\SyncHistoryRepository(
            $c->resolve(PDO::class)
        )
    );
    $this->container->alias(
        \FlujosDimension\Repositories\SyncHistoryRepository::class,
        'syncHistoryRepository'
    );

    $this->container->singleton(
        \FlujosDimension\Repositories\AsyncTaskRepository::class,
        fn ($c) => new \FlujosDimension\Repositories\AsyncTaskRepository(
            $c->resolve(PDO::class)
        )
    );
    $this->container->alias(
        \FlujosDimension\Repositories\AsyncTaskRepository::class,
        'asyncTaskRepository'
    );

    /* ---------- Integraciones externas ---------- */
    // HTTP clients
    $this->container->singleton(
        \FlujosDimension\Infrastructure\Http\OpenAIClient::class,
        fn ($c) => new \FlujosDimension\Infrastructure\Http\OpenAIClient(
            $c->resolve('httpClient'),
            $this->config->get('OPENAI_API_KEY')
        )
    );
    $this->container->singleton(
        \FlujosDimension\Infrastructure\Http\PipedriveClient::class,
        fn ($c) => new \FlujosDimension\Infrastructure\Http\PipedriveClient(
            $c->resolve('httpClient'),
            $this->config->get('PIPEDRIVE_API_TOKEN')
        )
    );
    $this->container->singleton(
        \FlujosDimension\Infrastructure\Http\RingoverClient::class,
        fn ($c) => new \FlujosDimension\Infrastructure\Http\RingoverClient(
            new \GuzzleHttp\Client(),
            $this->config
        )
    );

    // Servicios
    $this->container->singleton(
        \FlujosDimension\Services\AnalysisService::class,
        fn ($c) => new \FlujosDimension\Services\AnalysisService(
            $c->resolve(\FlujosDimension\Infrastructure\Http\OpenAIClient::class)
        )
    );
    $this->container->alias(\FlujosDimension\Services\AnalysisService::class, \FlujosDimension\Services\OpenAIService::class);

    $this->container->singleton(
        \FlujosDimension\Services\CRMService::class,
        fn ($c) => new \FlujosDimension\Services\CRMService(
            $c->resolve(\FlujosDimension\Infrastructure\Http\PipedriveClient::class),
            $c->resolve('callRepository')
        )
    );
    $this->container->alias(\FlujosDimension\Services\CRMService::class, \FlujosDimension\Services\PipedriveService::class);

    $this->container->singleton(
        \FlujosDimension\Services\CallService::class,
        fn ($c) => new \FlujosDimension\Services\CallService(
            $c->resolve(\FlujosDimension\Infrastructure\Http\RingoverClient::class)
        )
    );
    $this->container->alias(\FlujosDimension\Services\CallService::class, \FlujosDimension\Services\RingoverService::class);
    $this->container->alias(\FlujosDimension\Services\CallService::class, 'ringoverService');

    $this->container->singleton(
        \FlujosDimension\Services\SyncService::class,
        fn ($c) => new \FlujosDimension\Services\SyncService(
            $c->resolve(\FlujosDimension\Infrastructure\Http\RingoverClient::class),
            $c->resolve('callRepository')
        )
    );
    $this->container->alias(\FlujosDimension\Services\SyncService::class, 'syncService');

    /* ---------- Servicios de dominio ---------- */
    $this->container->singleton(
        \FlujosDimension\Services\AnalyticsService::class,
        fn ($c) => new \FlujosDimension\Services\AnalyticsService(
            $c->resolve('callRepository'),
            $c->resolve(\FlujosDimension\Services\AnalysisService::class),
            $c->resolve('logger')
        )
    );
    $this->container->alias(\FlujosDimension\Services\AnalyticsService::class, 'analyticsService');
}

   
    /**
     * Configurar manejo de errores
     */
    private function setupErrorHandling(): void
    {
        set_error_handler([$this->errorHandler, 'handleError']);
        set_exception_handler([$this->errorHandler, 'handleException']);
        register_shutdown_function([$this->errorHandler, 'handleShutdown']);
    }

    /**
     * Ensure recordings and voicemails storage directories exist and are writable.
     */
    private function ensureStorageDirectories(): void
    {
        $base = dirname(__DIR__, 2) . '/storage';
        foreach (['recordings', 'voicemails'] as $subdir) {
            $path = $base . '/' . $subdir;
            if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
                throw new \RuntimeException("Unable to create directory: {$path}");
            }
            if (!is_writable($path)) {
                throw new \RuntimeException("Directory {$path} is not writable");
            }
        }
    }
    
    /**
     * Obtener conexión a base de datos
     */
    private function getDatabaseConnection(): PDO
    {
        if ($this->database !== null) {
            return $this->database;
        }
        
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $this->config->get('DB_HOST'),
                $this->config->get('DB_PORT'),
                $this->config->get('DB_NAME')
            );

            $this->database = new PDO($dsn, $this->config->get('DB_USER'), $this->config->get('DB_PASS'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            
            return $this->database;
            
        } catch (PDOException $e) {
            $this->errorHandler->logError("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    /**
     * Definir rutas de la aplicación
     */
    private function defineRoutes(): void
    {
        $container = $this->container;

        // API Routes
        $this->router->group('/api', function($router) use ($container) {
            $router->get('/status', 'ApiController@status');
            $router->get('/health', 'ApiController@health');
            $router->post('/webhooks', 'WebhookController@create');

            $router->group('/v3', function($router) use ($container) {
                // Calls
                $router->get('/calls', 'CallsController@index');
                $router->get('/calls/{id}', 'CallsController@show');
                $router->post('/calls', 'CallsController@store');
                $router->put('/calls/{id}', 'CallsController@update');
                $router->delete('/calls/{id}', 'CallsController@destroy');
                $router->get('/calls/{id}/summary', 'N8nController@getSummary');

                // Analysis
                $router->post('/analysis/process', 'AnalysisController@process');
                $router->get('/analysis/batch/{id}', 'AnalysisController@batchStatus');
                $router->post('/analysis/sentiment/batch', 'AnalysisController@sentimentBatch');
                $router->get('/analysis/keywords', 'AnalysisController@keywords');

                // Config
                $router->get('/config', 'ConfigController@index');
                $router->put('/config/{key}', 'ConfigController@update');
                $router->post('/config/batch', 'ConfigController@batch');

                // Users
                $router->get('/users', 'UserController@index');
                $router->post('/users', 'UserController@create');
                $router->put('/users/{id}', 'UserController@update');
                $router->post('/users/{id}/permissions', 'UserController@permissions');

                // Reports
                $router->post('/reports/generate', 'ReportController@generate');
                $router->get('/reports/{id}', 'ReportController@status');
                $router->get('/reports/{id}/download', 'ReportController@download');
                $router->post('/reports/schedule', 'ReportController@schedule');

                // Webhooks
                $router->post('/webhooks', 'WebhookController@create');

                // Ringover specific webhooks
                $router->post('/webhooks/ringover/record-available', 'RingoverWebhookController@recordAvailable');
                $router->post('/webhooks/ringover/voicemail-available', 'RingoverWebhookController@voicemailAvailable');
                $router->get('/webhooks/ringover/health', function() {
                    return new Response('ok', 200);
                });

                // Allow preflight CORS requests and bypass CSRF
                $router->options('/webhooks/ringover/record-available', function() {
                    return new Response('', 204, [
                        'Access-Control-Allow-Origin' => '*',
                        'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                        'Access-Control-Allow-Headers' => 'Content-Type, X-Ringover-Signature'
                    ]);
                });
                $router->options('/webhooks/ringover/voicemail-available', function() {
                    return new Response('', 204, [
                        'Access-Control-Allow-Origin' => '*',
                        'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                        'Access-Control-Allow-Headers' => 'Content-Type, X-Ringover-Signature'
                    ]);
                });

                // Sync and token
                $router->post('/sync/hourly', 'SyncController@hourly');
                $router->post('/sync/manual', 'SyncController@manual');
                $router->get('/sync/status', 'SyncController@status');
                $router->post('/token/generate', 'TokenController@generate');
                $router->post('/token/validate', 'TokenController@verify');
                $router->get('/token/active', 'TokenController@active');
                $router->delete('/token/revoke', 'TokenController@revoke');

                $router->get('/debug/ringover/preview', function() use ($container) {
                    $since = $_GET['since'] ?? (new \DateTime('-7 days'))->format(DATE_ATOM);
                    $limit = (int)($_GET['limit'] ?? 5);
                    $client = $container->resolve(\FlujosDimension\Infrastructure\Http\RingoverClient::class);
                    $resp = $client->getCalls(['start_date' => $since, 'limit' => $limit]);
                    $list = $resp['call_list'] ?? [];
                    $mapped = array_map(function($c) {
                        $dir = $c['direction'] ?? null;
                        return [
                          'ringover_id'      => $c['cdr_id'] ?? null,
                          'call_id'          => $c['call_id'] ?? null,
                          'direction'        => ($dir==='in'||$dir==='inbound')?'inbound':(($dir==='out'||$dir==='outbound')?'outbound':null),
                          'status'           => $c['last_state'] ?? ($c['status'] ?? null),
                          'start_time'       => $c['start_time'] ?? null,
                          'answered_time'    => $c['answered_time'] ?? null,
                          'end_time'         => $c['end_time'] ?? null,
                          'incall_duration'  => $c['incall_duration'] ?? null,
                          'total_duration'   => $c['total_duration'] ?? null,
                          'queue_duration'   => $c['queue_duration'] ?? null,
                          'ringing_duration' => $c['ringing_duration'] ?? null,
                          'contact_number'   => $c['contact_number'] ?? null,
                          'recording_url'    => $c['record'] ?? ($c['record_url'] ?? null),
                        ];
                    }, $list);

                    return new Response(json_encode([
                       'count'  => count($mapped),
                       'sample' => array_slice($mapped, 0, min(3, count($mapped)))
                    ], JSON_UNESCAPED_UNICODE), 200, ['Content-Type'=>'application/json']);
                });

                $router->post('/sync/ringover/manual', function($request) use ($container) {
                    $since = $request->post('since') ?? (new \DateTime('-30 days'))->format(DATE_ATOM);
                    $until = $request->post('until') ?? null;
                    $svc = $container->resolve(\FlujosDimension\Services\SyncService::class);
                    $res = $svc->importRingover($since, $until, 100);
                    return new Response(json_encode($res, JSON_UNESCAPED_UNICODE), 200, ['Content-Type'=>'application/json']);
                });
            });
        });
        
        // Admin routes are handled by standalone scripts in the `admin/`
        // directory. Remove these entries until a dedicated controller is
        // implemented.
    }
    
    /**
     * Ejecutar la aplicación
     */
    public function run(): void
    {
        try {
            $this->defineRoutes();
            
            $response = $this->router->dispatch($this->request);
            
            if ($response instanceof Response) {
                $response->send();
            } else {
                // Respuesta simple
                echo $response;
            }
            
        } catch (Exception $e) {
            $this->errorHandler->handleException($e);
        }
    }
    
    /**
     * Obtener servicio del contenedor
     */
    public function service(string $name)
    {
        return $this->container->resolve($name);
    }
    
    /**
     * Obtener configuración
     */
    public function config(string $key, $default = null)
    {
        return $this->config->get($key, $default);
    }

    /**
     * Expose the underlying service container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }
}



