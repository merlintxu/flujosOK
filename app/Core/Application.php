<?php

declare(strict_types=1);

namespace FlujosDimension\Core;

use FlujosDimension\Core\{Config,Database,JWT};
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
    private array $config;
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

        // Valores por defecto
        $defaults = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'TIMEZONE' => 'Europe/Madrid',
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
            'JWT_EXPIRATION_HOURS' => '24'
        ];

        // Mezclar $_ENV con los valores por defecto
        $this->config = array_merge($defaults, $_ENV);

        // Configurar zona horaria
        date_default_timezone_set($this->config['TIMEZONE']);

    }
    
    /**
     * Inicializar componentes core
     */
    private function initializeCore(): void
    {
        $this->container = new Container();
        $this->request = new Request();
        $this->router = new Router($this->container);
        $this->errorHandler = new ErrorHandler($this->config['APP_DEBUG'] === 'true');
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
    $this->container->bind('config', $this->config);  // array de configuración

    // Conexión PDO única
    $this->container->bind(PDO::class, fn () => $this->getDatabaseConnection());
    // Alias para acceder a la base de datos por nombre
    $this->container->alias(PDO::class, 'database');

    /* ---------- Logger ---------- */
    $this->container->bind('logger', fn () =>
        new \FlujosDimension\Core\Logger(dirname(__DIR__, 2) . '/storage/logs')
    );

    /* ---------- HttpClient (Guzzle + retry) ---------- */
    $this->container->bind(
        \FlujosDimension\Infrastructure\Http\HttpClient::class,
        fn () => new \FlujosDimension\Infrastructure\Http\HttpClient()
    );
    // Alias corto por si te resulta práctico
    $this->container->alias(
        \FlujosDimension\Infrastructure\Http\HttpClient::class,
        'httpClient'
    );

    /* ---------- Repositorios ---------- */
    $this->container->bind(
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
    $this->container->bind(
        \FlujosDimension\Services\OpenAIService::class,
        fn ($c) => new \FlujosDimension\Services\OpenAIService(
            $c->resolve('httpClient'),
            $this->config['OPENAI_API_KEY']
        )
    );

    // Pipedrive
    $this->container->bind(
        \FlujosDimension\Services\PipedriveService::class,
        fn ($c) => new \FlujosDimension\Services\PipedriveService(
            $c->resolve('httpClient'),
            $this->config['PIPEDRIVE_API_TOKEN']
        )
    );

    // Ringover
    $this->container->bind(
        \FlujosDimension\Services\RingoverService::class,
        fn ($c) => new \FlujosDimension\Services\RingoverService($c)   // usa Container internamente
    );

    /* ---------- Servicios de dominio ---------- */
    $this->container->bind(
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
                $this->config['DB_HOST'],
                $this->config['DB_PORT'],
                $this->config['DB_NAME']
            );
            
            $this->database = new PDO($dsn, $this->config['DB_USER'], $this->config['DB_PASS'], [
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
            // Status y Health
            $router->get('/status', 'ApiController@status');
            $router->get('/health', 'ApiController@health');
            
            // Llamadas
            $router->get('/calls', 'CallController@index');
            $router->get('/calls/{id}', 'CallController@show');
            $router->post('/calls', 'CallController@store');
            $router->put('/calls/{id}', 'CallController@update');
            $router->delete('/calls/{id}', 'CallController@destroy');
            
            // Sincronización
            $router->post('/sync/manual', 'SyncController@manual');
            $router->get('/sync/status', 'SyncController@status');
            $router->post('/sync/hourly', 'SyncController@hourly');
            $router->post('/sync/batch', 'BatchController@process');
            
            // Analytics
            $router->get('/analytics/dashboard', 'AnalyticsController@dashboard');
            $router->get('/analytics/stats', 'AnalyticsController@stats');
            $router->get('/analytics/reports', 'ReportController@generate');
            
            // Ringover
            $router->get('/ringover/calls', 'RingoverController@getCalls');
            $router->get('/ringover/team', 'RingoverController@getTeam');
            $router->get('/ringover/transcripts', 'RingoverController@getTranscripts');
            
            // OpenAI
            $router->post('/openai/transcribe', 'OpenAIController@transcribe');
            $router->post('/openai/analyze', 'OpenAIController@analyze');
            $router->post('/openai/batch', 'OpenAIController@batch');
            
            // Tokens JWT
            $router->post('/token/generate', 'TokenController@generate');
            $router->post('/token/validate', 'TokenController@validate');
            $router->delete('/token/revoke', 'TokenController@revoke');
            
            // Tests de APIs
            $router->get('/test/ringover', 'TestController@ringover');
            $router->get('/test/openai', 'TestController@openai');
            $router->get('/test/pipedrive', 'TestController@pipedrive');
        });
        
        // Admin Routes
        $this->router->group('/admin', function($router) {
            $router->get('/', 'AdminController@dashboard');
            $router->get('/login', 'AdminController@login');
            $router->post('/login', 'AdminController@authenticate');
            $router->get('/logout', 'AdminController@logout');
            $router->get('/env-editor', 'AdminController@envEditor');
            $router->post('/env-save', 'AdminController@envSave');
        });
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
        return $this->config[$key] ?? $default;
    }
}



