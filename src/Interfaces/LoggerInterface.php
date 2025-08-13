<?php
/**
 * Logger Interface
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Interfaces;

/**
 * Interface for logging debug messages
 */
interface LoggerInterface {

    /**
     * Log a debug message
     *
     * @param string $type    Message type (info, success, warning, error, filter, action)
     * @param string $message The message content
     * @param array  $data    Optional data to include
     * @return void
     */
    public function log(string $type, string $message, array $data = []): void;

    /**
     * Get all logged messages
     *
     * @return array Array of logged messages
     */
    public function getMessages(): array;

    /**
     * Clear all logged messages
     *
     * @return void
     */
    public function clearMessages(): void;

    /**
     * Get messages by type
     *
     * @param string $type Message type to filter by
     * @return array Array of messages of the specified type
     */
    public function getMessagesByType(string $type): array;
}
