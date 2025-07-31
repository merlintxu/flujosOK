<?php

namespace FlujosDimension\Core;

/**
 * Clase Request - Flujos Dimension v4.2
 * Manejo de peticiones HTTP
 */
class Request
{
    private array $query;
    private array $post;
    private array $server;
    private array $headers;
    private ?string $body;
    private ?array $jsonBody;
    
    public function __construct(?string $body = null)
    {
        $this->query = $_GET ?? [];
        $this->post = $_POST ?? [];
        $this->server = $_SERVER ?? [];
        $this->headers = $this->parseHeaders();
        $this->body = $body ?? file_get_contents('php://input');
        $this->jsonBody = null;
    }
    
    /**
     * Obtener parámetro GET
     */
    public function get(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }
    
    /**
     * Obtener parámetro POST
     */
    public function post(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }
    
    /**
     * Obtener todos los parámetros GET
     */
    public function query(): array
    {
        return $this->query;
    }
    
    /**
     * Obtener todos los parámetros POST
     */
    public function postData(): array
    {
        return $this->post;
    }
    
    /**
     * Obtener el cuerpo de la petición
     */
    public function getBody(): ?string
    {
        return $this->body;
    }
    
    /**
     * Obtener el cuerpo como JSON
     */
    public function getJsonBody(): ?array
    {
        if ($this->jsonBody === null && $this->body) {
            $this->jsonBody = json_decode($this->body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON body: ' . json_last_error_msg());
            }
        }

        return $this->jsonBody;
    }
    
    /**
     * Obtener método HTTP
     */
    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }
    
    /**
     * Obtener URI de la petición
     */
    public function getUri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }
    
    /**
     * Obtener path de la URI (sin query string)
     */
    public function getPath(): string
    {
        $uri = $this->getUri();
        return parse_url($uri, PHP_URL_PATH) ?? '/';
    }
    
    /**
     * Obtener header específico
     */
    public function getHeader(string $name): ?string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? null;
    }
    
    /**
     * Obtener todos los headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
    
    /**
     * Verificar si es petición AJAX
     */
    public function isAjax(): bool
    {
        return strtolower($this->getHeader('x-requested-with') ?? '') === 'xmlhttprequest';
    }
    
    /**
     * Verificar si es petición JSON
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('content-type') ?? '';
        return str_contains(strtolower($contentType), 'application/json');
    }
    
    /**
     * Verificar si es método específico
     */
    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }
    
    /**
     * Obtener IP del cliente
     */
    public function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($this->server[$key])) {
                $ip = trim(explode(',', $this->server[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Obtener User Agent
     */
    public function getUserAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Obtener protocolo (HTTP/HTTPS)
     */
    public function getScheme(): string
    {
        $https = $this->server['HTTPS'] ?? '';
        $port = $this->server['SERVER_PORT'] ?? 80;
        
        return ($https && $https !== 'off') || $port == 443 ? 'https' : 'http';
    }
    
    /**
     * Obtener host
     */
    public function getHost(): string
    {
        return $this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost';
    }
    
    /**
     * Obtener URL completa
     */
    public function getUrl(): string
    {
        return $this->getScheme() . '://' . $this->getHost() . $this->getUri();
    }
    
    /**
     * Verificar si tiene parámetro
     */
    public function has(string $key): bool
    {
        return isset($this->query[$key]) || isset($this->post[$key]);
    }
    
    /**
     * Obtener parámetro de cualquier fuente (GET, POST, JSON)
     */
    public function input(string $key, $default = null)
    {
        // Primero buscar en POST
        if (isset($this->post[$key])) {
            return $this->post[$key];
        }
        
        // Luego en GET
        if (isset($this->query[$key])) {
            return $this->query[$key];
        }
        
        // Finalmente en JSON body
        $jsonBody = $this->getJsonBody();
        if ($jsonBody && isset($jsonBody[$key])) {
            return $jsonBody[$key];
        }
        
        return $default;
    }
    
    /**
     * Obtener todos los inputs
     */
    public function all(): array
    {
        $data = array_merge($this->query, $this->post);
        
        $jsonBody = $this->getJsonBody();
        if ($jsonBody) {
            $data = array_merge($data, $jsonBody);
        }
        
        return $data;
    }
    
    /**
     * Obtener solo ciertos campos
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }
    
    /**
     * Obtener todos excepto ciertos campos
     */
    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }
    
    /**
     * Validar que existen ciertos campos
     */
    public function validate(array $required): array
    {
        $data = $this->all();
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            throw new \InvalidArgumentException('Missing required fields: ' . implode(', ', $missing));
        }
        
        return $data;
    }
    
    /**
     * Parsear headers HTTP
     */
    private function parseHeaders(): array
    {
        $headers = [];
        
        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = $value;
            }
        }
        
        // Headers especiales
        if (isset($this->server['CONTENT_TYPE'])) {
            $headers['content-type'] = $this->server['CONTENT_TYPE'];
        }
        
        if (isset($this->server['CONTENT_LENGTH'])) {
            $headers['content-length'] = $this->server['CONTENT_LENGTH'];
        }
        
        return $headers;
    }
    
    /**
     * Obtener información de archivos subidos
     */
    public function files(): array
    {
        return $_FILES ?? [];
    }
    
    /**
     * Verificar si tiene archivo subido
     */
    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK;
    }
    
    /**
     * Obtener archivo subido
     */
    public function file(string $key): ?array
    {
        return $this->hasFile($key) ? $_FILES[$key] : null;
    }
}

