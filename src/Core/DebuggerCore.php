<?php
/**
 * Debugger Core Implementation
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Core;

use KissPlugins\WooCouponDebugger\Interfaces\DebuggerInterface;
use KissPlugins\WooCouponDebugger\Interfaces\LoggerInterface;
use KissPlugins\WooCouponDebugger\Interfaces\HookTrackerInterface;
use KissPlugins\WooCouponDebugger\Interfaces\CartSimulatorInterface;
use Exception;

/**
 * Core debugger functionality
 */
class DebuggerCore implements DebuggerInterface {

    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Hook tracker instance
     *
     * @var HookTrackerInterface
     */
    private $hookTracker;

    /**
     * Cart simulator instance
     *
     * @var CartSimulatorInterface
     */
    private $cartSimulator;

    /**
     * Maximum execution time for debugging operations
     *
     * @var int
     */
    private $maxExecutionTime = 30;

    /**
     * Constructor
     *
     * @param LoggerInterface        $logger        Logger instance
     * @param HookTrackerInterface   $hookTracker   Hook tracker instance
     * @param CartSimulatorInterface $cartSimulator Cart simulator instance
     */
    public function __construct(
        LoggerInterface $logger,
        HookTrackerInterface $hookTracker,
        CartSimulatorInterface $cartSimulator
    ) {
        $this->logger = $logger;
        $this->hookTracker = $hookTracker;
        $this->cartSimulator = $cartSimulator;
    }



    /**
     * Test a coupon with given parameters
     *
     * @param string $coupon_code The coupon code to test
     * @param array  $product_ids Array of product IDs to test with
     * @param int    $user_id     User ID to simulate
     * @return bool|\WP_Error True if coupon applied successfully, WP_Error on failure
     */
    public function testCoupon(string $coupon_code, array $product_ids = [], int $user_id = 0) {
        $this->logger->log('info', sprintf(
            __('Starting coupon test for: "%s"', 'wc-sc-debugger'),
            $coupon_code
        ));

        // Store original state with error handling
        try {
            $original_cart_contents = WC()->cart->get_cart_contents();
            $original_applied_coupons = WC()->cart->get_applied_coupons();
            $original_user_id = get_current_user_id();

            // Safely get session data
            $original_session_data = array();
            if (WC()->session && method_exists(WC()->session, 'get_session_data')) {
                $original_session_data = WC()->session->get_session_data();
            }

            // Safely backup notices
            $original_notices = array();
            if (WC()->session) {
                $original_notices = WC()->session->get('wc_notices', array());
                if (!is_array($original_notices)) {
                    $original_notices = array();
                }
            }
        } catch (Exception $e) {
            $this->logger->log('error', sprintf(
                __('Failed to backup cart state: %s', 'wc-sc-debugger'),
                $e->getMessage()
            ));
            return new \WP_Error('backup_failed', __('Could not backup cart state for testing.', 'wc-sc-debugger'));
        }

        try {
            // Check for Smart Coupons plugin and log version info
            if (class_exists('WC_Smart_Coupons')) {
                $this->logger->log('info', __('WooCommerce Smart Coupons plugin detected', 'wc-sc-debugger'));

                // Check PHP version compatibility
                if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
                    $this->logger->log('warning', sprintf(
                        __('Running PHP %s - Smart Coupons may have compatibility issues with PHP 8+', 'wc-sc-debugger'),
                        PHP_VERSION
                    ));
                }
            }

            // Start hook tracking
            $this->hookTracker->startTracking();

            // Clear the current cart and session for a clean test
            WC()->cart->empty_cart(true);
            if (WC()->session) {
                WC()->session->set('wc_notices', array());
                WC()->session->set('sc_coupon_valid', null);
                WC()->session->set('sc_coupon_error', null);
                WC()->session->set('wc_sc_cart_smart_coupons', array());
            }

            // Simulate user login if a user ID is provided
            if ($user_id > 0 && get_user_by('id', $user_id)) {
                wp_set_current_user($user_id);
                $this->logger->log('info', sprintf(__('Simulating user ID: %d', 'wc-sc-debugger'), $user_id));
            } else {
                wp_set_current_user(0);
                $this->logger->log('info', __('Simulating guest user.', 'wc-sc-debugger'));
            }

            // Add products to the cart
            $products_added = false;
            if (!empty($product_ids)) {
                foreach ($product_ids as $product_id) {
                    $product = wc_get_product($product_id);
                    if ($product && $product->is_purchasable() && $product->is_in_stock()) {
                        $cart_item_key = WC()->cart->add_to_cart($product_id, 1);
                        if ($cart_item_key) {
                            $products_added = true;
                            $this->logger->log('info', sprintf(
                                __('Added product to cart: %s (ID: %d)', 'wc-sc-debugger'),
                                $product->get_name(),
                                $product_id
                            ));
                        } else {
                            $this->logger->log('warning', sprintf(
                                __('Failed to add product ID %d to cart.', 'wc-sc-debugger'),
                                $product_id
                            ));
                        }
                    } else {
                        $this->logger->log('warning', sprintf(
                            __('Product ID %d is not purchasable or not in stock.', 'wc-sc-debugger'),
                            $product_id
                        ));
                    }
                }
            }

            // If cart is still empty, add a dummy product
            if (WC()->cart->is_empty()) {
                $dummy_product_id = $this->getOrCreateDummyProduct();
                if ($dummy_product_id) {
                    $cart_item_key = WC()->cart->add_to_cart($dummy_product_id, 1);
                    if ($cart_item_key) {
                        $this->logger->log('info', sprintf(
                            __('Cart was empty, added dummy product (ID: %d) for testing general coupons.', 'wc-sc-debugger'),
                            $dummy_product_id
                        ));
                    } else {
                        throw new Exception(__('Failed to add dummy product to cart.', 'wc-sc-debugger'));
                    }
                } else {
                    throw new Exception(__('Could not create or find a dummy product for testing.', 'wc-sc-debugger'));
                }
            }

            // Calculate totals before applying coupon
            WC()->cart->calculate_totals();

            $this->logger->log('info', sprintf(
                __('Cart total before coupon: %s', 'wc-sc-debugger'),
                wc_price(WC()->cart->get_total('edit'))
            ));

            $this->logger->log('info', sprintf(
                __('Attempting to apply coupon: "%s"', 'wc-sc-debugger'),
                $coupon_code
            ));

            // Apply the coupon with Smart Coupons error handling
            try {
                $coupon_applied = WC()->cart->apply_coupon($coupon_code);
            } catch (\TypeError $e) {
                // Handle Smart Coupons PHP 8+ compatibility errors
                if (strpos($e->getMessage(), 'Cannot access offset of type string on string') !== false) {
                    $this->logger->log('error', sprintf(
                        __('Smart Coupons PHP 8+ compatibility error: %s', 'wc-sc-debugger'),
                        $e->getMessage()
                    ));
                    $this->logger->log('warning', __('This is a known issue with WooCommerce Smart Coupons and PHP 8+. The coupon may still be valid but cannot be properly tested due to plugin compatibility issues.', 'wc-sc-debugger'));
                    $coupon_applied = false;
                } else {
                    // Re-throw other TypeErrors
                    throw $e;
                }
            }

            if (is_wp_error($coupon_applied)) {
                $this->logger->log('error', sprintf(
                    __('Failed to apply coupon: %s', 'wc-sc-debugger'),
                    $coupon_applied->get_error_message()
                ));
                $result = false;
            } elseif (!$coupon_applied) {
                // Check for WooCommerce notices
                $notices = wc_get_notices('error');
                if (!empty($notices)) {
                    foreach ($notices as $notice) {
                        $notice_text = is_array($notice) ? $notice['notice'] : $notice;
                        $this->logger->log('error', sprintf(
                            __('WooCommerce Notice: %s', 'wc-sc-debugger'),
                            $notice_text
                        ));
                    }
                }
                $this->logger->log('error', __('Coupon could not be applied. Check coupon validity and restrictions.', 'wc-sc-debugger'));
                $result = false;
            } else {
                WC()->cart->calculate_totals();
                $cart_total = WC()->cart->get_total('edit');
                $discount_total = WC()->cart->get_discount_total();
                $this->logger->log('success', __('Coupon applied successfully!', 'wc-sc-debugger'));
                $this->logger->log('success', sprintf(
                    __('New Cart Total: %s', 'wc-sc-debugger'),
                    wc_price($cart_total)
                ));
                $this->logger->log('success', sprintf(
                    __('Discount Amount: %s', 'wc-sc-debugger'),
                    wc_price($discount_total)
                ));
                $result = true;
            }

        } catch (Exception $e) {
            $this->logger->log('error', sprintf(
                __('Exception during coupon test: %s', 'wc-sc-debugger'),
                $e->getMessage()
            ));
            $result = new \WP_Error('test_exception', $e->getMessage());
        }

        // Stop hook tracking
        $this->hookTracker->stopTracking();

        // Restore original state
        $this->restoreCartState($original_cart_contents, $original_applied_coupons, $original_session_data, $original_notices, $original_user_id);

        return $result;
    }

    /**
     * Start tracking hooks and filters
     *
     * @return void
     */
    public function startHookTracking(): void {
        $this->hookTracker->startTracking();
    }

    /**
     * Stop tracking hooks and filters
     *
     * @return void
     */
    public function stopHookTracking(): void {
        $this->hookTracker->stopTracking();
    }

    /**
     * Get debug messages
     *
     * @return array Array of debug messages
     */
    public function getDebugMessages(): array {
        return $this->logger->getMessages();
    }

    /**
     * Clear debug messages
     *
     * @return void
     */
    public function clearDebugMessages(): void {
        $this->logger->clearMessages();
    }

    /**
     * Restore cart state after testing
     *
     * @param array $original_cart_contents Original cart contents
     * @param array $original_applied_coupons Original applied coupons
     * @param array $original_session_data Original session data
     * @param array $original_notices Original notices
     * @param int $original_user_id Original user ID
     */
    private function restoreCartState($original_cart_contents, $original_applied_coupons, $original_session_data, $original_notices, $original_user_id) {
        try {
            // Clear cart
            WC()->cart->empty_cart(true);

            // Restore cart contents
            foreach ($original_cart_contents as $cart_item_key => $cart_item) {
                $product_id = $cart_item['product_id'];
                $quantity = $cart_item['quantity'];
                $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
                $variation = isset($cart_item['variation']) ? $cart_item['variation'] : array();

                WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
            }

            // Restore session data
            if (WC()->session && !empty($original_session_data)) {
                foreach ($original_session_data as $key => $value) {
                    WC()->session->set($key, $value);
                }
            }

            // Restore notices
            if (WC()->session) {
                WC()->session->set('wc_notices', $original_notices);
            }

            // Restore applied coupons
            foreach ($original_applied_coupons as $coupon_code) {
                WC()->cart->apply_coupon($coupon_code);
            }

            // Restore user
            wp_set_current_user($original_user_id);

            // Recalculate totals
            WC()->cart->calculate_totals();

        } catch (Exception $e) {
            error_log('WC SC Debugger: Failed to restore cart state - ' . $e->getMessage());
        }
    }

    /**
     * Get or create a simple dummy product for testing purposes.
     *
     * @return int|bool Product ID on success, false on failure.
     */
    private function getOrCreateDummyProduct() {
        $product_id = get_option('wc_sc_debugger_dummy_product_id');

        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product && $product->exists()) {
                return $product_id;
            }
        }

        // Create a new dummy product
        try {
            $new_product = new \WC_Product_Simple();
            $new_product->set_name('Debugger Test Product');
            $new_product->set_status('private');
            $new_product->set_catalog_visibility('hidden');
            $new_product->set_price(100);
            $new_product->set_regular_price(100);
            $new_product->set_manage_stock(false);
            $new_product->set_stock_status('instock');
            $new_product->set_virtual(true);

            $new_product_id = $new_product->save();

            if ($new_product_id) {
                update_option('wc_sc_debugger_dummy_product_id', $new_product_id);
                return $new_product_id;
            }
        } catch (Exception $e) {
            error_log('WC SC Debugger: Failed to create dummy product - ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Set maximum execution time
     *
     * @param int $seconds Maximum execution time in seconds
     * @return void
     */
    public function setMaxExecutionTime(int $seconds): void {
        $this->maxExecutionTime = $seconds;
    }

    /**
     * Get maximum execution time
     *
     * @return int Maximum execution time in seconds
     */
    public function getMaxExecutionTime(): int {
        return $this->maxExecutionTime;
    }
}
