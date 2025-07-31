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
        fn () => new \FlujosDimension\Infrastructure\Http\HttpClient()
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

    /* ---------- Integraciones externas ---------- */
    // OpenAI
    $this->container->singleton(
        \FlujosDimension\Services\OpenAIService::class,
        fn ($c) => new \FlujosDimension\Services\OpenAIService(
            $c->resolve('httpClient'),
            $this->config->get('OPENAI_API_KEY')
        )
    );

    // Pipedrive
    $this->container->singleton(
        \FlujosDimension\Services\PipedriveService::class,
        fn ($c) => new \FlujosDimension\Services\PipedriveService(
            $c->resolve('httpClient'),
            $this->config->get('PIPEDRIVE_API_TOKEN')
        )
    );

    // Ringover
    $this->container->singleton(
        \FlujosDimension\Services\RingoverService::class,
        fn ($c) => new \FlujosDimension\Services\RingoverService($c)   // usa Container internamente
    );

    /* ---------- Servicios de dominio ---------- */
    $this->container->singleton(
        \FlujosDimension\Services\AnalyticsService::class,            // <- ruta exacta: app\Services\AnalyticsService.php
        fn ($c) => new \FlujosDimension\Services\AnalyticsService(
            $c->resolve('callRepository'),
            $c->resolve(\FlujosDimension\Services\OpenAIService::class)
        )
    );
    $this->container->alias(
        \FlujosDimension\Services\AnalyticsService::class,
        'analyticsService'
    );
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
        // API Routes
        $this->router->group('/api', function($router) {
            $router->get('/status', 'ApiController@status');
            $router->get('/health', 'ApiController@health');

            $router->group('/v3', function($router) {
                // Calls
                $router->get('/calls', 'CallsController@index');
                $router->get('/calls/{id}', 'CallsController@show');
                $router->post('/calls', 'CallsController@store');
                $router->put('/calls/{id}', 'CallsController@update');
                $router->delete('/calls/{id}', 'CallsController@destroy');

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

                // Sync and token
                $router->post('/sync/hourly', 'SyncController@hourly');
                $router->post('/sync/manual', 'SyncController@manual');
                $router->get('/sync/status', 'SyncController@status');
                $router->post('/token/generate', 'TokenController@generate');
                $router->post('/token/validate', 'TokenController@verify');
                $router->delete('/token/revoke', 'TokenController@revoke');
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



