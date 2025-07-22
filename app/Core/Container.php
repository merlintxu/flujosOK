<?php

namespace FlujosDimension\Core;

use Exception;
use ReflectionClass;
use ReflectionParameter;

/**
 * Contenedor de Inyección de Dependencias - Flujos Dimension v4.2
 * Migrado y mejorado desde v3
 */
class Container
{
    private array $bindings = [];
    private array $instances = [];
    private array $aliases = [];
    
    /**
     * Registrar un binding en el contenedor
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }
    
    /**
     * Registrar un singleton en el contenedor
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }
    
    /**
     * Registrar una instancia existente
     */
    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }
    
    /**
     * Crear un alias para un binding
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }
    
    /**
     * Resolver una dependencia del contenedor
     */
    public function resolve(string $abstract)
    {
        // Verificar si es un alias
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }
        
        // Si ya existe una instancia, devolverla
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        
        // Si no hay binding, intentar resolver automáticamente
        if (!isset($this->bindings[$abstract])) {
            return $this->build($abstract);
        }
        
        $binding = $this->bindings[$abstract];
        $concrete = $binding['concrete'];
        
        // Si es una función, ejecutarla
        if (is_callable($concrete)) {
            $instance = $concrete($this);
        } else {
            $instance = $this->build($concrete);
        }
        
        // Si es compartido, guardarlo como singleton
        if ($binding['shared']) {
            $this->instances[$abstract] = $instance;
        }
        
        return $instance;
    }
    
    /**
     * Construir una instancia de clase con inyección automática
     */
    private function build(string $concrete)
    {
        // Si no es una clase, devolverla tal como está
        if (!class_exists($concrete)) {
            return $concrete;
        }
        
        $reflector = new ReflectionClass($concrete);
        
        // Si no se puede instanciar, lanzar excepción
        if (!$reflector->isInstantiable()) {
            throw new Exception("Class {$concrete} is not instantiable");
        }
        
        $constructor = $reflector->getConstructor();
        
        // Si no tiene constructor, crear instancia simple
        if ($constructor === null) {
            return new $concrete;
        }
        
        // Resolver dependencias del constructor
        $dependencies = $this->resolveDependencies($constructor->getParameters());
        
        return $reflector->newInstanceArgs($dependencies);
    }
    
    /**
     * Resolver dependencias de parámetros
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $dependency = $this->resolveDependency($parameter);
            $dependencies[] = $dependency;
        }
        
        return $dependencies;
    }
    
    /**
     * Resolver una dependencia individual
     */
    private function resolveDependency(ReflectionParameter $parameter)
    {
        // Si tiene un tipo de clase, resolverlo
        if ($parameter->getType() && !$parameter->getType()->isBuiltin()) {
            $className = $parameter->getType()->getName();
            return $this->resolve($className);
        }
        
        // Si tiene valor por defecto, usarlo
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        
        // Si es opcional, devolver null
        if ($parameter->allowsNull()) {
            return null;
        }
        
        throw new Exception("Cannot resolve dependency for parameter: {$parameter->getName()}");
    }
    
    /**
     * Verificar si existe un binding
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || 
               isset($this->instances[$abstract]) || 
               isset($this->aliases[$abstract]);
    }
    
    /**
     * Obtener todos los bindings registrados
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
    
    /**
     * Limpiar el contenedor
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
    }
    
    /**
     * Método mágico para resolver dependencias
     */
    public function __get(string $name)
    {
        return $this->resolve($name);
    }
    
    /**
     * Método mágico para verificar si existe un binding
     */
    public function __isset(string $name): bool
    {
        return $this->bound($name);
    }
    
    /**
     * Crear una instancia con parámetros específicos
     */
    public function make(string $abstract, array $parameters = [])
    {
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }
        
        if (!class_exists($abstract)) {
            throw new Exception("Class {$abstract} does not exist");
        }
        
        $reflector = new ReflectionClass($abstract);
        
        if (!$reflector->isInstantiable()) {
            throw new Exception("Class {$abstract} is not instantiable");
        }
        
        $constructor = $reflector->getConstructor();
        
        if ($constructor === null) {
            return new $abstract;
        }
        
        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            
            if (isset($parameters[$name])) {
                $dependencies[] = $parameters[$name];
            } else {
                $dependencies[] = $this->resolveDependency($parameter);
            }
        }
        
        return $reflector->newInstanceArgs($dependencies);
    }
    
    /**
     * Ejecutar un callback con inyección de dependencias
     */
    public function call(callable $callback, array $parameters = [])
    {
        if (is_array($callback)) {
            [$class, $method] = $callback;
            
            if (is_string($class)) {
                $class = $this->resolve($class);
            }
            
            $reflector = new \ReflectionMethod($class, $method);
        } else {
            $reflector = new \ReflectionFunction($callback);
        }
        
        $dependencies = [];
        foreach ($reflector->getParameters() as $parameter) {
            $name = $parameter->getName();
            
            if (isset($parameters[$name])) {
                $dependencies[] = $parameters[$name];
            } else {
                $dependencies[] = $this->resolveDependency($parameter);
            }
        }
        
        return $reflector->invokeArgs(
            isset($class) ? $class : null, 
            $dependencies
        );
    }
}

