<?php
/**
 * Cart Simulator Interface
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Interfaces;

/**
 * Interface for cart simulation functionality
 */
interface CartSimulatorInterface {

    /**
     * Backup current cart state
     *
     * @return array Backed up cart state
     */
    public function backupCartState(): array;

    /**
     * Restore cart state from backup
     *
     * @param array $backup_state The backed up cart state
     * @return void
     */
    public function restoreCartState(array $backup_state): void;

    /**
     * Clear the cart for testing
     *
     * @return void
     */
    public function clearCart(): void;

    /**
     * Add products to cart for testing
     *
     * @param array $product_ids Array of product IDs to add
     * @return bool True if products were added successfully
     */
    public function addProductsToCart(array $product_ids): bool;

    /**
     * Apply a coupon to the cart
     *
     * @param string $coupon_code The coupon code to apply
     * @return bool|\WP_Error True if successful, WP_Error on failure
     */
    public function applyCoupon(string $coupon_code);

    /**
     * Get or create a dummy product for testing
     *
     * @return int|false Product ID on success, false on failure
     */
    public function getOrCreateDummyProduct();
}
