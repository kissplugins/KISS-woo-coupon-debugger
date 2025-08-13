<?php
/**
 * Container Interface
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Interfaces;

/**
 * Interface for dependency injection container
 */
interface ContainerInterface {

    /**
     * Bind a service to the container
     *
     * @param string $id Service identifier
     * @param mixed  $concrete Service implementation or factory
     * @return void
     */
    public function bind(string $id, $concrete): void;

    /**
     * Bind a singleton service to the container
     *
     * @param string $id Service identifier
     * @param mixed  $concrete Service implementation or factory
     * @return void
     */
    public function singleton(string $id, $concrete): void;

    /**
     * Get a service from the container
     *
     * @param string $id Service identifier
     * @return mixed The service instance
     * @throws \Exception If service not found
     */
    public function get(string $id);

    /**
     * Check if a service is bound to the container
     *
     * @param string $id Service identifier
     * @return bool True if service is bound
     */
    public function has(string $id): bool;

    /**
     * Resolve a class with its dependencies
     *
     * @param string $class Class name to resolve
     * @return mixed The resolved class instance
     * @throws \Exception If class cannot be resolved
     */
    public function resolve(string $class);
}
