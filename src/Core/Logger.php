<?php
/**
 * Debug Logger Implementation
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Core;

use KissPlugins\WooCouponDebugger\Interfaces\LoggerInterface;

/**
 * Logger for debug messages
 */
class Logger implements LoggerInterface {

    /**
     * Array to store debugging messages
     *
     * @var array
     */
    private $messages = [];

    /**
     * Maximum number of messages to store
     *
     * @var int
     */
    private $maxMessages = 1000;

    /**
     * Log a debug message
     *
     * @param string $type    Message type (info, success, warning, error, filter, action)
     * @param string $message The message content
     * @param array  $data    Optional data to include
     * @return void
     */
    public function log(string $type, string $message, array $data = []): void {
        // Prevent excessive logging
        if (count($this->messages) >= $this->maxMessages) {
            $this->messages[] = [
                'type'    => 'warning',
                'message' => __('Debug message limit reached. Some messages may be omitted.', 'wc-sc-debugger'),
                'data'    => [],
                'timestamp' => time(),
            ];
            return;
        }

        $this->messages[] = [
            'type'      => $type,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => time(),
        ];
    }

    /**
     * Get all logged messages
     *
     * @return array Array of logged messages
     */
    public function getMessages(): array {
        return $this->messages;
    }

    /**
     * Clear all logged messages
     *
     * @return void
     */
    public function clearMessages(): void {
        $this->messages = [];
    }

    /**
     * Get messages by type
     *
     * @param string $type Message type to filter by
     * @return array Array of messages of the specified type
     */
    public function getMessagesByType(string $type): array {
        return array_filter($this->messages, function($message) use ($type) {
            return $message['type'] === $type;
        });
    }

    /**
     * Set maximum number of messages
     *
     * @param int $max Maximum number of messages
     * @return void
     */
    public function setMaxMessages(int $max): void {
        $this->maxMessages = $max;
    }

    /**
     * Get message count
     *
     * @return int Number of logged messages
     */
    public function getMessageCount(): int {
        return count($this->messages);
    }

    /**
     * Get messages since timestamp
     *
     * @param int $timestamp Unix timestamp
     * @return array Messages since the given timestamp
     */
    public function getMessagesSince(int $timestamp): array {
        return array_filter($this->messages, function($message) use ($timestamp) {
            return $message['timestamp'] >= $timestamp;
        });
    }
}
