<?php
/**
 * Dependency Injection Container
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Container;

use KissPlugins\WooCouponDebugger\Interfaces\ContainerInterface;
use ReflectionClass;
use ReflectionParameter;
use Exception;

/**
 * Simple dependency injection container implementation
 */
class Container implements ContainerInterface {

    /**
     * Container bindings
     *
     * @var array
     */
    private $bindings = [];

    /**
     * Singleton instances
     *
     * @var array
     */
    private $instances = [];

    /**
     * Bind a service to the container
     *
     * @param string $id Service identifier
     * @param mixed  $concrete Service implementation or factory
     * @return void
     */
    public function bind(string $id, $concrete): void {
        $this->bindings[$id] = [
            'concrete' => $concrete,
            'singleton' => false,
        ];
    }

    /**
     * Bind a singleton service to the container
     *
     * @param string $id Service identifier
     * @param mixed  $concrete Service implementation or factory
     * @return void
     */
    public function singleton(string $id, $concrete): void {
        $this->bindings[$id] = [
            'concrete' => $concrete,
            'singleton' => true,
        ];
    }

    /**
     * Get a service from the container
     *
     * @param string $id Service identifier
     * @return mixed The service instance
     * @throws Exception If service not found
     */
    public function get(string $id) {
        // Check if we have a singleton instance
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Check if service is bound
        if (!$this->has($id)) {
            // Try to resolve as class name
            if (class_exists($id)) {
                return $this->resolve($id);
            }
            throw new Exception("Service '{$id}' not found in container");
        }

        $binding = $this->bindings[$id];
        $concrete = $binding['concrete'];

        // Resolve the service
        if (is_callable($concrete)) {
            $instance = $concrete($this);
        } elseif (is_string($concrete) && class_exists($concrete)) {
            $instance = $this->resolve($concrete);
        } else {
            $instance = $concrete;
        }

        // Store singleton instances
        if ($binding['singleton']) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Check if a service is bound to the container
     *
     * @param string $id Service identifier
     * @return bool True if service is bound
     */
    public function has(string $id): bool {
        return isset($this->bindings[$id]);
    }

    /**
     * Resolve a class with its dependencies
     *
     * @param string $class Class name to resolve
     * @return mixed The resolved class instance
     * @throws Exception If class cannot be resolved
     */
    public function resolve(string $class) {
        try {
            $reflection = new ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new Exception("Class '{$class}' not found: " . $e->getMessage());
        }

        if (!$reflection->isInstantiable()) {
            throw new Exception("Class '{$class}' is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        // If no constructor, just create instance
        if (is_null($constructor)) {
            return new $class;
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor dependencies
     *
     * @param ReflectionParameter[] $parameters Constructor parameters
     * @return array Resolved dependencies
     * @throws Exception If dependency cannot be resolved
     */
    private function resolveDependencies(array $parameters): array {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (is_null($type)) {
                // No type hint, check if has default value
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception("Cannot resolve parameter '{$parameter->getName()}' without type hint");
                }
            } elseif ($type instanceof \ReflectionNamedType) {
                $typeName = $type->getName();

                // Try to resolve from container or as class
                try {
                    $dependencies[] = $this->get($typeName);
                } catch (Exception $e) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } elseif ($type->allowsNull()) {
                        $dependencies[] = null;
                    } else {
                        throw new Exception("Cannot resolve dependency '{$typeName}': " . $e->getMessage());
                    }
                }
            } else {
                throw new Exception("Union types not supported for parameter '{$parameter->getName()}'");
            }
        }

        return $dependencies;
    }
}
