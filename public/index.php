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

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/env.php';

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

