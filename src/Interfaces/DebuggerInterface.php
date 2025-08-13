<?php
/**
 * Debugger Interface
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Interfaces;

/**
 * Interface for coupon debugger implementations
 */
interface DebuggerInterface {

    /**
     * Test a coupon with given parameters
     *
     * @param string $coupon_code The coupon code to test
     * @param array  $product_ids Array of product IDs to test with
     * @param int    $user_id     User ID to simulate
     * @return bool|\WP_Error True if coupon applied successfully, WP_Error on failure
     */
    public function testCoupon(string $coupon_code, array $product_ids = [], int $user_id = 0);

    /**
     * Start tracking hooks and filters
     *
     * @return void
     */
    public function startHookTracking(): void;

    /**
     * Stop tracking hooks and filters
     *
     * @return void
     */
    public function stopHookTracking(): void;

    /**
     * Get debug messages
     *
     * @return array Array of debug messages
     */
    public function getDebugMessages(): array;

    /**
     * Clear debug messages
     *
     * @return void
     */
    public function clearDebugMessages(): void;
}
