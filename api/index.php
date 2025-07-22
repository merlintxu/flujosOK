<?php
/**
 * Flujos Dimension v4.1 - API Principal
 * API REST con autenticación JWT y endpoints funcionales
 * 
 * @version 4.1.0
 * @author Manus AI
 */

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuración básica
define('API_ROOT', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));

// Autoloader
spl_autoload_register(function ($class) {
    $paths = [
        PROJECT_ROOT . '/app/core/' . $class . '.php',
        PROJECT_ROOT . '/app/services/' . $class . '.php',
        PROJECT_ROOT . '/app/controllers/' . $class . '.php',
        PROJECT_ROOT . '/app/models/' . $class . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

/**
 * Clase principal de la API
 */
class FlujosApi
{
    private $config;
    private $db;
    private $jwt;
    private $apiService;
    private $method;
    private $endpoint;
    private $params;
    private $isAuthenticated = false;
    private $currentUser = null;
    
    public function __construct()
    {
        $this->initializeServices();
        $this->parseRequest();
        $this->handleRequest();
    }
    
    /**
     * Inicializar servicios
     */
    private function initializeServices()
    {
        try {
            $this->config = \\FlujosDimension\\Core\\Config::getInstance();
            $this->jwt = new JWT();
            $this->apiService = new ApiService();
            
        } catch (Exception $e) {
            $this->sendError('Service initialization failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Parsear petición HTTP
     */
    private function parseRequest()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        
        // Obtener endpoint desde PATH_INFO o REQUEST_URI
        $path = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($path, PHP_URL_PATH);
        $path = trim($path, '/');
        
        // Remover prefijo api si existe
        if (strpos($path, 'api/') === 0) {
            $path = substr($path, 4);
        }
        
        $this->endpoint = explode('/', $path);
        $this->params = $_GET;
        
        // Para POST/PUT, obtener datos del body
        if (in_array($this->method, ['POST', 'PUT', 'PATCH'])) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if ($data) {
                $this->params = array_merge($this->params, $data);
            } else {
                $this->params = array_merge($this->params, $_POST);
            }
        }
    }
    
    /**
     * Verificar autenticación JWT
     */
    private function checkAuthentication()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (empty($authHeader)) {
            return false;
        }
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return false;
        }
        
        $token = $matches[1];
        $payload = $this->jwt->validateToken($token);
        
        if ($payload) {
            $this->isAuthenticated = true;
            $this->currentUser = $payload;
            return true;
        }
        
        return false;
    }
    
    /**
     * Manejar petición
     */
    private function handleRequest()
    {
        try {
            // Endpoints públicos que no requieren autenticación
            $publicEndpoints = ['status', 'health'];
            
            $mainEndpoint = $this->endpoint[0] ?? '';
            
            // Verificar autenticación para endpoints protegidos
            if (!in_array($mainEndpoint, $publicEndpoints)) {
                if (!$this->checkAuthentication()) {
                    $this->sendError('Authentication required', 401);
                }
            }
            
            // Enrutar según endpoint
            switch ($mainEndpoint) {
                case 'status':
                    $this->handleStatus();
                    break;
                    
                case 'health':
                    $this->handleHealth();
                    break;
                    
                case 'sync':
                    $this->handleSync();
                    break;
                    
                case 'calls':
                    $this->handleCalls();
                    break;
                    
                case 'api-test':
                    $this->handleApiTest();
                    break;
                    
                case 'token':
                    $this->handleToken();
                    break;
                    
                default:
                    $this->sendError('Endpoint not found', 404);
            }
            
        } catch (Exception $e) {
            $this->logError('API Error: ' . $e->getMessage());
            $this->sendError('Internal server error', 500);
        }
    }
    
    /**
     * Endpoint de estado del sistema
     */
    private function handleStatus()
    {
        $status = [
            'status' => 'online',
            'version' => '4.1.0',
            'timestamp' => date('c'),
            'database' => $this->db->isConnected() ? 'connected' : 'disconnected',
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB',
            'uptime' => $this->getUptime()
        ];
        
        $this->sendSuccess($status);
    }
    
    /**
     * Endpoint de salud del sistema
     */
    private function handleHealth()
    {
        $health = [
            'overall_status' => 'healthy',
            'checks' => [
                'database' => $this->db->isConnected(),
                'config' => $this->config->validateRequiredConfig(),
                'storage' => is_writable(PROJECT_ROOT . '/storage'),
                'apis' => $this->apiService->getAllApiStatus()
            ],
            'timestamp' => date('c')
        ];
        
        // Determinar estado general
        $allHealthy = true;
        foreach ($health['checks'] as $key => $check) {
            if ($key === 'apis') {
                foreach ($check as $api => $apiStatus) {
                    if (!$apiStatus['success']) {
                        $allHealthy = false;
                        break;
                    }
                }
            } elseif (!$check) {
                $allHealthy = false;
            }
        }
        
        $health['overall_status'] = $allHealthy ? 'healthy' : 'unhealthy';
        
        $this->sendSuccess($health);
    }
    
    /**
     * Endpoint de sincronización
     */
    private function handleSync()
    {
        $action = $this->endpoint[1] ?? '';
        
        switch ($action) {
            case 'hourly':
                $this->handleHourlySync();
                break;
                
            case 'status':
                $this->handleSyncStatus();
                break;
                
            default:
                $this->sendError('Sync action not found', 404);
        }
    }
    
    /**
     * Sincronización horaria
     */
    private function handleHourlySync()
    {
        if ($this->method !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            $result = [
                'sync_started' => date('c'),
                'actions_performed' => [],
                'errors' => []
            ];
            
            // Sincronizar llamadas de Ringover
            try {
                $calls = $this->apiService->getRingoverCalls(50);
                $syncedCalls = $this->syncCallsToDatabase($calls);
                $result['actions_performed'][] = "Synced $syncedCalls calls from Ringover";
            } catch (Exception $e) {
                $result['errors'][] = 'Ringover sync failed: ' . $e->getMessage();
            }
            
            // Limpiar tokens expirados
            try {
                $cleanedTokens = $this->jwt->cleanupExpiredTokens();
                $result['actions_performed'][] = "Cleaned $cleanedTokens expired tokens";
            } catch (Exception $e) {
                $result['errors'][] = 'Token cleanup failed: ' . $e->getMessage();
            }
            
            // Limpiar logs antiguos
            try {
                $cleanedLogs = $this->cleanupOldLogs();
                $result['actions_performed'][] = "Cleaned $cleanedLogs old log entries";
            } catch (Exception $e) {
                $result['errors'][] = 'Log cleanup failed: ' . $e->getMessage();
            }
            
            $result['sync_completed'] = date('c');
            $result['success'] = empty($result['errors']);
            
            $this->sendSuccess($result);
            
        } catch (Exception $e) {
            $this->sendError('Sync failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Estado de sincronización
     */
    private function handleSyncStatus()
    {
        $status = [
            'last_sync' => $this->getLastSyncTime(),
            'next_sync' => $this->getNextSyncTime(),
            'sync_enabled' => true,
            'sync_interval' => '1 hour',
            'total_calls' => $this->getTotalCallsCount(),
            'calls_today' => $this->getTodayCallsCount()
        ];
        
        $this->sendSuccess($status);
    }
    
    /**
     * Endpoint de llamadas
     */
    private function handleCalls()
    {
        switch ($this->method) {
            case 'GET':
                $this->getCalls();
                break;
                
            case 'POST':
                $this->createCall();
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Obtener llamadas
     */
    private function getCalls()
    {
        $limit = (int)($this->params['limit'] ?? 20);
        $offset = (int)($this->params['offset'] ?? 0);
        $date = $this->params['date'] ?? null;
        
        $limit = min($limit, 100); // Máximo 100 registros
        
        try {
            $calls = $this->db->select(
                "SELECT * FROM calls " . 
                ($date ? "WHERE DATE(created_at) = ? " : "") .
                "ORDER BY created_at DESC LIMIT ? OFFSET ?",
                $date ? [$date, $limit, $offset] : [$limit, $offset]
            );
            
            $total = $this->db->selectOne(
                "SELECT COUNT(*) as total FROM calls" . 
                ($date ? " WHERE DATE(created_at) = ?" : ""),
                $date ? [$date] : []
            )['total'];
            
            $this->sendSuccess([
                'calls' => $calls,
                'pagination' => [
                    'total' => (int)$total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Failed to get calls: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Crear nueva llamada
     */
    private function createCall()
    {
        $requiredFields = ['phone_number', 'direction'];
        
        foreach ($requiredFields as $field) {
            if (empty($this->params[$field])) {
                $this->sendError("Field '$field' is required", 400);
            }
        }
        
        try {
            $callId = $this->db->insert(
                "INSERT INTO calls (ringover_id, phone_number, direction, status, duration, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                [
                    $this->params['ringover_id'] ?? null,
                    $this->params['phone_number'],
                    $this->params['direction'],
                    $this->params['status'] ?? 'missed',
                    (int)($this->params['duration'] ?? 0)
                ]
            );
            
            $this->sendSuccess(['call_id' => $callId, 'message' => 'Call created successfully'], 201);
            
        } catch (Exception $e) {
            $this->sendError('Failed to create call: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Test de APIs externas
     */
    private function handleApiTest()
    {
        $api = $this->endpoint[1] ?? '';
        
        switch ($api) {
            case 'ringover':
                $result = $this->apiService->testRingoverApi();
                break;
                
            case 'openai':
                $result = $this->apiService->testOpenAiApi();
                break;
                
            case 'pipedrive':
                $result = $this->apiService->testPipedriveApi();
                break;
                
            case 'all':
                $result = $this->apiService->getAllApiStatus();
                break;
                
            default:
                $this->sendError('API test not found', 404);
        }
        
        $this->sendSuccess($result);
    }
    
    /**
     * Gestión de tokens
     */
    private function handleToken()
    {
        $action = $this->endpoint[1] ?? '';
        
        switch ($action) {
            case 'generate':
                if ($this->method !== 'POST') {
                    $this->sendError('Method not allowed', 405);
                }
                
                $token = $this->jwt->generateToken([
                    'purpose' => 'api_access',
                    'generated_via' => 'api'
                ]);
                
                $this->sendSuccess([
                    'token' => $token,
                    'expires_in_hours' => $this->config->get('JWT_EXPIRATION_HOURS', 24)
                ]);
                break;
                
            case 'validate':
                $token = $this->params['token'] ?? '';
                $payload = $this->jwt->validateToken($token);
                
                $this->sendSuccess([
                    'valid' => $payload !== false,
                    'payload' => $payload
                ]);
                break;
                
            default:
                $this->sendError('Token action not found', 404);
        }
    }
    
    /**
     * Métodos auxiliares
     */
    private function syncCallsToDatabase($calls)
    {
        $synced = 0;
        
        if (!isset($calls['data']) || !is_array($calls['data'])) {
            return $synced;
        }
        
        foreach ($calls['data'] as $call) {
            try {
                // Verificar si la llamada ya existe
                $existing = $this->db->selectOne(
                    "SELECT id FROM calls WHERE ringover_id = ?",
                    [$call['id']]
                );
                
                if (!$existing) {
                    $this->db->insert(
                        "INSERT INTO calls (ringover_id, phone_number, direction, status, duration, created_at) VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $call['id'],
                            $call['phone_number'] ?? '',
                            $call['direction'] ?? 'inbound',
                            $call['status'] ?? 'missed',
                            (int)($call['duration'] ?? 0),
                            $call['created_at'] ?? date('Y-m-d H:i:s')
                        ]
                    );
                    $synced++;
                }
            } catch (Exception $e) {
                $this->logError('Error syncing call: ' . $e->getMessage());
            }
        }
        
        return $synced;
    }
    
    private function cleanupOldLogs()
    {
        $retentionDays = (int)$this->config->get('LOG_RETENTION_DAYS', 30);
        
        return $this->db->delete(
            "DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$retentionDays]
        );
    }
    
    private function getUptime()
    {
        $uptimeFile = PROJECT_ROOT . '/storage/cache/uptime.txt';
        if (file_exists($uptimeFile)) {
            $startTime = (int)file_get_contents($uptimeFile);
            return gmdate('H:i:s', time() - $startTime);
        }
        return 'Unknown';
    }
    
    private function getLastSyncTime()
    {
        $syncFile = PROJECT_ROOT . '/storage/cache/last_sync.txt';
        if (file_exists($syncFile)) {
            return file_get_contents($syncFile);
        }
        return null;
    }
    
    private function getNextSyncTime()
    {
        $lastSync = $this->getLastSyncTime();
        if ($lastSync) {
            return date('c', strtotime($lastSync) + 3600); // +1 hora
        }
        return date('c', time() + 3600);
    }
    
    private function getTotalCallsCount()
    {
        return (int)$this->db->selectOne("SELECT COUNT(*) as total FROM calls")['total'];
    }
    
    private function getTodayCallsCount()
    {
        return (int)$this->db->selectOne("SELECT COUNT(*) as total FROM calls WHERE DATE(created_at) = CURDATE()")['total'];
    }
    
    /**
     * Enviar respuesta exitosa
     */
    private function sendSuccess($data, $code = 200)
    {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Enviar respuesta de error
     */
    private function sendError($message, $code = 400)
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Registrar error en logs
     */
    private function logError($message)
    {
        $logFile = PROJECT_ROOT . '/storage/logs/api.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [ERROR] $message" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Inicializar API
try {
    new FlujosApi();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'API initialization failed',
        'timestamp' => date('c')
    ]);
}
?>

