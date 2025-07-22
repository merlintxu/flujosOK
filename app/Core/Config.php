<?php
/**
 * Flujos Dimension v4.1 - Configuration Manager
 * Manejo robusto de variables de entorno y configuración
 * 
 * @version 4.1.0
 * @author Manus AI
 */
namespace FlujosDimension\Core;
class Config
{
    private static $instance = null;
    private $config = [];
    private $envLoaded = false;
    
    private function __construct()
    {
        $this->loadEnvironmentVariables();
    }
    
    /**
     * Singleton pattern
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Cargar variables de entorno desde archivo .env
     */
    public function loadEnvironmentVariables()
    {
        if ($this->envLoaded) {
            return true;
        }

        // Load via Dotenv bootstrap
        $bootstrap = dirname(__DIR__, 2) . '/bootstrap/env.php';
        if (file_exists($bootstrap)) {
            require_once $bootstrap;
        }

        $this->config = array_merge($this->config, $_ENV);
        $this->envLoaded = true;
        $this->logInfo('Environment variables loaded via Dotenv. Total: ' . count($this->config));
        return true;
    }
    
    /**
     * Obtener valor de configuración
     */
    public function get($key, $default = null)
    {
        // Prioridad: config cargado > $_ENV > getenv() > default
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }
        
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        $envValue = getenv($key);
        if ($envValue !== false) {
            return $envValue;
        }
        
        return $default;
    }
    
    /**
     * Establecer valor de configuración
     */
    public function set($key, $value)
    {
        $this->config[$key] = $value;
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
    
    /**
     * Verificar si existe una clave de configuración
     */
    public function has($key)
    {
        return isset($this->config[$key]) || isset($_ENV[$key]) || getenv($key) !== false;
    }
    
    /**
     * Obtener toda la configuración
     */
    public function all()
    {
        return array_merge($_ENV, $this->config);
    }
    
    /**
     * Verificar si las variables de entorno están cargadas
     */
    public function isEnvLoaded()
    {
        return $this->envLoaded;
    }
    
    /**
     * Guardar configuración en archivo .env
     */
    public function saveToEnvFile($newConfig)
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        
        try {
            $content = "# Flujos Dimension v4.1 - Variables de Entorno\n";
            $content .= "# Actualizado: " . date('Y-m-d H:i:s') . "\n\n";
            
            // Agrupar por secciones
            $sections = [
                'ADMINISTRADOR DEL SISTEMA' => ['ADMIN_USER', 'ADMIN_PASS'],
                'BASE DE DATOS' => ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'],
                'API RINGOVER' => ['RINGOVER_API_URL', 'RINGOVER_API_TOKEN'],
                'API OPENAI' => ['OPENAI_API_URL', 'OPENAI_API_KEY'],
                'API PIPEDRIVE' => ['PIPEDRIVE_API_URL', 'PIPEDRIVE_API_TOKEN'],
                'SEGURIDAD Y JWT' => ['JWT_SECRET', 'JWT_EXPIRATION_HOURS', 'APP_ENV', 'APP_DEBUG'],
                'CONFIGURACIÓN DE API' => ['MAX_API_REQUESTS_PER_HOUR', 'API_TIMEOUT', 'API_LOG_LEVEL', 'RATE_LIMIT_ENABLED'],
                'CONFIGURACIÓN DE SISTEMA' => ['TIMEZONE', 'LOG_RETENTION_DAYS', 'BACKUP_RETENTION_DAYS', 'MAX_UPLOAD_SIZE', 'CACHE_ENABLED', 'CACHE_TTL', 'SESSION_LIFETIME']
            ];
            
            foreach ($sections as $sectionName => $keys) {
                $content .= "# ===================================\n";
                $content .= "# $sectionName\n";
                $content .= "# ===================================\n";
                
                foreach ($keys as $key) {
                    $value = $newConfig[$key] ?? $this->get($key, '');
                    $content .= "$key=$value\n";
                }
                
                $content .= "\n";
            }
            
            // Agregar otras variables no categorizadas
            foreach ($newConfig as $key => $value) {
                $found = false;
                foreach ($sections as $sectionKeys) {
                    if (in_array($key, $sectionKeys)) {
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $content .= "$key=$value\n";
                }
            }
            
            if (file_put_contents($envFile, $content) !== false) {
                $this->logInfo("Configuration saved to .env file successfully");
                // Recargar configuración
                $this->loadEnvironmentVariables();
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logError("Error saving .env file: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener configuración de base de datos
     */
    public function getDatabaseConfig()
    {
        return [
            'host' => $this->get('DB_HOST', 'localhost'),
            'port' => $this->get('DB_PORT', '3306'),
            'dbname' => $this->get('DB_NAME', ''),
            'username' => $this->get('DB_USER', ''),
            'password' => $this->get('DB_PASS', '')
        ];
    }
    
    /**
     * Obtener configuración de APIs
     */
    public function getApiConfig($api)
    {
        switch (strtolower($api)) {
            case 'ringover':
                return [
                    'url' => $this->get('RINGOVER_API_URL', 'https://public-api.ringover.com/v2'),
                    'token' => $this->get('RINGOVER_API_TOKEN', '')
                ];
                
            case 'openai':
                return [
                    'url' => $this->get('OPENAI_API_URL', 'https://api.openai.com/v1'),
                    'key' => $this->get('OPENAI_API_KEY', '')
                ];
                
            case 'pipedrive':
                return [
                    'url' => $this->get('PIPEDRIVE_API_URL', 'https://api.pipedrive.com/v1'),
                    'token' => $this->get('PIPEDRIVE_API_TOKEN', '')
                ];
                
            default:
                return null;
        }
    }
    
    /**
     * Obtener configuración de JWT
     */
    public function getJwtConfig()
    {
        return [
            'secret' => $this->get('JWT_SECRET', ''),
            'expiration_hours' => (int)$this->get('JWT_EXPIRATION_HOURS', 24),
            'algorithm' => 'HS256'
        ];
    }
    
    /**
     * Validar configuración requerida
     */
    public function validateRequiredConfig()
    {
        $required = [
            'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
            'ADMIN_USER', 'ADMIN_PASS',
            'JWT_SECRET'
        ];
        
        $missing = [];
        
        foreach ($required as $key) {
            if (!$this->has($key) || empty($this->get($key))) {
                $missing[] = $key;
            }
        }
        
        if (!empty($missing)) {
            $this->logWarning("Missing required configuration: " . implode(', ', $missing));
            return false;
        }
        
        return true;
    }
    
    /**
     * Registrar información en logs
     */
    private function logInfo($message)
    {
        $this->writeLog('info', $message);
    }
    
    /**
     * Registrar advertencia en logs
     */
    private function logWarning($message)
    {
        $this->writeLog('warning', $message);
    }
    
    /**
     * Registrar error en logs
     */
    private function logError($message)
    {
        $this->writeLog('error', $message);
    }
    
    /**
     * Escribir en archivo de log
     */
    private function writeLog($level, $message)
    {
        $logFile = dirname(__DIR__, 2) . '/storage/logs/config.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Prevenir clonación
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>

