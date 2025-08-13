<?php
/**
 * Hook Tracker Interface
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Interfaces;

/**
 * Interface for tracking WordPress hooks and filters
 */
interface HookTrackerInterface {

    /**
     * Start tracking hooks and filters
     *
     * @return void
     */
    public function startTracking(): void;

    /**
     * Stop tracking hooks and filters
     *
     * @return void
     */
    public function stopTracking(): void;

    /**
     * Check if tracking is currently active
     *
     * @return bool True if tracking is active
     */
    public function isTrackingActive(): bool;

    /**
     * Track a filter execution
     *
     * @param mixed $value The filtered value
     * @param mixed ...$args Additional filter arguments
     * @return mixed The original value
     */
    public function trackFilter($value, ...$args);

    /**
     * Track an action execution
     *
     * @param mixed ...$args Action arguments
     * @return void
     */
    public function trackAction(...$args): void;
}
