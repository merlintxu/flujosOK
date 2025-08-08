<?php

namespace FlujosDimension\Core;

class Router
{
    private Container $container;
    private array $routes = [];
    private string $groupPrefix = '';
    
    public function __construct(Container $container)
    {
        $this->container = $container;
    }
    
    public function get(string $path, $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }
    
    public function post(string $path, $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }
    
    public function put(string $path, $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }
    
    public function delete(string $path, $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    public function options(string $path, $handler): void
    {
        $this->addRoute('OPTIONS', $path, $handler);
    }
    
    public function group(string $prefix, callable $callback): void
    {
        $oldPrefix = $this->groupPrefix;
        $this->groupPrefix = $oldPrefix . $prefix;
        $callback($this);
        $this->groupPrefix = $oldPrefix;
    }
    
    private function addRoute(string $method, string $path, $handler): void
    {
        $fullPath = $this->groupPrefix . $path;
        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler
        ];
    }
    
    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
                return $this->callHandler($route['handler'], $request, $path, $route['path']);
            }
        }
        
        return new Response(json_encode(['error' => 'Route not found']), 404, ['Content-Type' => 'application/json']);
    }
    
    private function matchPath(string $routePath, string $requestPath): bool
    {
        $routePattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $routePath);
        return preg_match('#^' . $routePattern . '$#', $requestPath);
    }
    
    private function callHandler($handler, Request $request, string $path, string $routePath)
    {
        if (is_callable($handler) && !is_string($handler)) {
            return call_user_func($handler, $request);
        }

        [$controllerName, $method] = explode('@', $handler);
        $controllerClass = "FlujosDimension\\Controllers\\$controllerName";

        if (!class_exists($controllerClass)) {
            return new Response(json_encode(['error' => 'Controller not found']), 404, ['Content-Type' => 'application/json']);
        }

        $controller = new $controllerClass($this->container, $request);

        if (!method_exists($controller, $method)) {
            return new Response(json_encode(['error' => 'Method not found']), 404, ['Content-Type' => 'application/json']);
        }

        // Extraer parámetros de la URL
        $params = $this->extractParams($routePath, $path);

        return call_user_func_array([$controller, $method], $params);
    }
    
    private function extractParams(string $routePath, string $requestPath): array
    {
        preg_match_all('/\{([^}]+)\}/', $routePath, $paramNames);
        $routePattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $routePath);
        preg_match('#^' . $routePattern . '$#', $requestPath, $paramValues);
        
        array_shift($paramValues);
        return $paramValues;
    }
}
