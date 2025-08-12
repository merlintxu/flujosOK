<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Container;
use FlujosDimension\Core\Request;
use FlujosDimension\Core\Response;

/**
 * Controlador Base - Flujos Dimension v4.2
 * Funcionalidades comunes para todos los controladores
 */
abstract class BaseController
{
    protected Container $container;
    protected Request $request;
    protected $logger;
    protected array $config;
    
    public function __construct(Container $container, Request $request)
    {
        $this->container = $container;
        $this->request = $request;
        $this->logger = $container->resolve('logger');
        $this->config = $container->resolve('config');
    }
    
    /**
     * Crear respuesta JSON
     */
    protected function jsonResponse(array $data, int $status = 200): Response
    {
        // Add correlation ID to response headers
        $headers = [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-Correlation-ID',
            'X-Correlation-ID' => $this->request->getCorrelationId()
        ];

        // Add correlation ID to response data if not already present
        if (!isset($data['correlation_id'])) {
            $data['correlation_id'] = $this->request->getCorrelationId();
        }

        return new Response(json_encode($data), $status, $headers);
    }
    
    /**
     * Crear respuesta de error
     */
    protected function errorResponse(string $message, int $status = 400): Response
    {
        return $this->jsonResponse([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'correlation_id' => $this->request->getCorrelationId()
        ], $status);
    }
    
    /**
     * Crear respuesta de éxito
     */
    protected function successResponse($data = null, string $message = 'Success'): Response
    {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return $this->jsonResponse($response);
    }

    /**
     * Normalize and trim input strings. Phone fields are converted to E.164.
     */
    protected function normalizeInput(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                if (str_contains($key, 'phone')) {
                    $value = $this->normalizePhone($value);
                }
                $data[$key] = $value;
            }
        }
        return $data;
    }

    /**
     * Convert a phone number to E.164 format.
     */
    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        return $digits !== '' ? '+' . $digits : '';
    }
    
    /**
     * Validar datos de entrada
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $ruleList = explode('|', $rule);
            
            foreach ($ruleList as $singleRule) {
                $ruleParts = explode(':', $singleRule);
                $ruleName = $ruleParts[0];
                $ruleValue = $ruleParts[1] ?? null;
                
                switch ($ruleName) {
                    case 'required':
                        if (!isset($data[$field]) || empty($data[$field])) {
                            $errors[$field][] = "Field {$field} is required";
                        }
                        break;
                        
                    case 'string':
                        if (isset($data[$field]) && !is_string($data[$field])) {
                            $errors[$field][] = "Field {$field} must be a string";
                        }
                        break;
                        
                    case 'integer':
                        if (isset($data[$field]) && !is_int($data[$field]) && !ctype_digit($data[$field])) {
                            $errors[$field][] = "Field {$field} must be an integer";
                        }
                        break;
                        
                    case 'email':
                        if (isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = "Field {$field} must be a valid email";
                        }
                        break;
                        
                    case 'min':
                        if (isset($data[$field]) && strlen($data[$field]) < (int)$ruleValue) {
                            $errors[$field][] = "Field {$field} must be at least {$ruleValue} characters";
                        }
                        break;
                        
                    case 'max':
                        if (isset($data[$field]) && strlen($data[$field]) > (int)$ruleValue) {
                            $errors[$field][] = "Field {$field} must not exceed {$ruleValue} characters";
                        }
                        break;
                        
                    case 'in':
                        $allowedValues = explode(',', $ruleValue);
                        if (isset($data[$field]) && !in_array($data[$field], $allowedValues)) {
                            $errors[$field][] = "Field {$field} must be one of: " . implode(', ', $allowedValues);
                        }
                        break;
                }
            }
        }
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . json_encode($errors));
        }
        
        return $data;
    }
    
    /**
     * Verificar autenticación JWT
     */
    protected function requireAuth(): ?array
    {
        $authHeader = $this->request->getHeader('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new UnauthorizedHttpException('Authorization token required');
        }
        
        $token = substr($authHeader, 7);
        
        try {
            $jwtService = $this->container->resolve('jwtService');
            return $jwtService->validateToken($token);
        } catch (\Exception $e) {
            throw new UnauthorizedHttpException('Invalid or expired token');
        }
    }
    
    /**
     * Verificar permisos de administrador
     */
    protected function requireAdmin(): void
    {
        $user = $this->requireAuth();
        
        if (!isset($user['is_admin']) || !$user['is_admin']) {
            throw new \ForbiddenHttpException('Admin privileges required');
        }
    }
    
    /**
     * Obtener parámetros de paginación
     */
    protected function getPaginationParams(): array
    {
        $orderBy = $this->request->get('order_by', 'created_at');
        if (!preg_match('/^[A-Za-z0-9_]+$/', $orderBy)) {
            $orderBy = 'created_at';
        }

        $direction = strtoupper($this->request->get('direction', 'DESC'));
        $direction = $direction === 'ASC' ? 'ASC' : 'DESC';

        return [
            'page' => max(1, (int) $this->request->get('page', 1)),
            'per_page' => min(100, max(10, (int) $this->request->get('per_page', 20))),
            'order_by' => $orderBy,
            'direction' => $direction
        ];
    }
    
    /**
     * Crear respuesta paginada
     */
    protected function paginatedResponse(array $data, int $total, array $params): Response
    {
        $totalPages = ceil($total / $params['per_page']);
        
        return $this->jsonResponse([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $params['page'],
                'per_page' => $params['per_page'],
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $params['page'] < $totalPages,
                'has_prev' => $params['page'] > 1
            ]
        ]);
    }
    
    /**
     * Registrar actividad del usuario
     */
    protected function logActivity(string $action, array $data = []): void
    {
        try {
            $this->logger->info("User activity: {$action}", array_merge($data, [
                'ip' => $this->request->getClientIp(),
                'user_agent' => $this->request->getHeader('User-Agent'),
                'timestamp' => date('Y-m-d H:i:s')
            ]));
        } catch (\Exception $e) {
            // No fallar si no se puede registrar la actividad
        }
    }
    
    /**
     * Obtener servicio del contenedor
     */
    protected function service(string $name)
    {
        return $this->container->resolve($name);
    }
    
    /**
     * Obtener configuración
     */
    protected function config(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Sanitizar entrada de usuario
     */
    protected function sanitize(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Verificar límite de tasa (rate limiting)
     */
    protected function checkRateLimit(string $key, int $maxRequests = 60, int $windowSeconds = 60): bool
    {
        try {
            $cache = $this->container->resolve('cache');
            $currentCount = $cache->get($key, 0);
            
            if ($currentCount >= $maxRequests) {
                return false;
            }
            
            $cache->set($key, $currentCount + 1, $windowSeconds);
            return true;
            
        } catch (\Exception $e) {
            // Si falla el cache, permitir la petición
            return true;
        }
    }
    
    /**
     * Manejar errores de forma consistente
     */
    protected function handleError(\Exception $e, string $context = ''): Response
    {
        $message = $context ? "{$context}: {$e->getMessage()}" : $e->getMessage();
        
        $this->logger->error($message, [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // En producción, no mostrar detalles del error
        if ($this->config('APP_ENV') === 'production') {
            $message = 'An error occurred while processing your request';
        }
        
        $status = 500;
        if ($e instanceof \InvalidArgumentException) {
            $status = 400;
        } elseif ($e instanceof UnauthorizedHttpException) {
            $status = 401;
        } elseif ($e instanceof ForbiddenHttpException) {
            $status = 403;
        } elseif ($e instanceof NotFoundHttpException) {
            $status = 404;
        }
        
        return $this->errorResponse($message, $status);
    }
}

/**
 * Excepciones HTTP personalizadas
 */
class UnauthorizedHttpException extends \Exception {}
class ForbiddenHttpException extends \Exception {}
class NotFoundHttpException extends \Exception {}

