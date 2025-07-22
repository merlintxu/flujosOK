<?php

/**
 * Flujos Dimension v4.2 - Punto de entrada principal
 * Sistema completo migrado desde v3 con todas las funcionalidades
 */

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Configuración de zona horaria
date_default_timezone_set('Europe/Madrid');

// Autoloader simple para las clases
spl_autoload_register(function ($class) {
    // Convertir namespace a ruta de archivo
    $prefix = 'FlujosDimension\\';
    $baseDir = __DIR__ . '/../app/';
    
    // Verificar si la clase usa nuestro namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Obtener el nombre relativo de la clase
    $relativeClass = substr($class, $len);
    
    // Reemplazar namespace separators con directory separators
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    // Si el archivo existe, incluirlo
    if (file_exists($file)) {
        require $file;
    }
});

// Incluir clases adicionales necesarias
require_once __DIR__ . '/../app/Core/Request.php';
require_once __DIR__ . '/../app/Core/Response.php';
require_once __DIR__ . '/../app/Core/Router.php';
require_once __DIR__ . '/../app/Core/ErrorHandler.php';
require_once __DIR__ . '/../app/Core/CacheManager.php';

use FlujosDimension\Core\Application;

try {
    // Crear y ejecutar la aplicación
    $app = new Application();
    $app->run();
    
} catch (Exception $e) {
    // Manejo de errores de último recurso
    http_response_code(500);
    
    $errorResponse = [
        'success' => false,
        'error' => 'Internal server error',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // En desarrollo, mostrar detalles del error
    if (getenv('APP_ENV') !== 'production') {
        $errorResponse['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    // Log del error
    error_log("Fatal error in Flujos Dimension v4.2: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Respuesta JSON
    header('Content-Type: application/json');
    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
}

