<?php
/**
 * Cart Simulator Implementation
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Cart;

use KissPlugins\WooCouponDebugger\Interfaces\CartSimulatorInterface;
use KissPlugins\WooCouponDebugger\Interfaces\LoggerInterface;
use Exception;

/**
 * Handles cart simulation for coupon testing
 */
class CartSimulator implements CartSimulatorInterface {

    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger Logger instance
     */
    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * Backup current cart state
     *
     * @return array Backed up cart state
     */
    public function backupCartState(): array {
        try {
            $backup = [
                'cart_contents'     => WC()->cart->get_cart_contents(),
                'applied_coupons'   => WC()->cart->get_applied_coupons(),
                'user_id'           => get_current_user_id(),
                'session_data'      => [],
                'notices'           => [],
            ];
            
            // Safely get session data
            if (WC()->session && method_exists(WC()->session, 'get_session_data')) {
                $backup['session_data'] = WC()->session->get_session_data();
            }
            
            // Safely backup notices
            if (WC()->session) {
                $notices = WC()->session->get('wc_notices', []);
                $backup['notices'] = is_array($notices) ? $notices : [];
            }
            
            $this->logger->log('info', __('Cart state backed up successfully', 'wc-sc-debugger'));
            return $backup;
            
        } catch (Exception $e) {
            $this->logger->log('error', sprintf(__('Failed to backup cart state: %s', 'wc-sc-debugger'), $e->getMessage()));
            return [];
        }
    }

    /**
     * Restore cart state from backup
     *
     * @param array $backup_state The backed up cart state
     * @return void
     */
    public function restoreCartState(array $backup_state): void {
        try {
            // Clear cart
            WC()->cart->empty_cart(true);

            // Restore cart contents
            if (!empty($backup_state['cart_contents'])) {
                foreach ($backup_state['cart_contents'] as $cart_item_key => $cart_item) {
                    $product_id = $cart_item['product_id'];
                    $quantity = $cart_item['quantity'];
                    $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
                    $variation = isset($cart_item['variation']) ? $cart_item['variation'] : [];
                    
                    WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
                }
            }

            // Restore session data
            if (WC()->session && !empty($backup_state['session_data'])) {
                foreach ($backup_state['session_data'] as $key => $value) {
                    WC()->session->set($key, $value);
                }
            }

            // Restore notices
            if (WC()->session && isset($backup_state['notices'])) {
                WC()->session->set('wc_notices', $backup_state['notices']);
            }

            // Restore applied coupons
            if (!empty($backup_state['applied_coupons'])) {
                foreach ($backup_state['applied_coupons'] as $coupon_code) {
                    WC()->cart->apply_coupon($coupon_code);
                }
            }

            // Restore user
            if (isset($backup_state['user_id'])) {
                wp_set_current_user($backup_state['user_id']);
            }

            // Recalculate totals
            WC()->cart->calculate_totals();

            $this->logger->log('info', __('Cart state restored successfully', 'wc-sc-debugger'));

        } catch (Exception $e) {
            $this->logger->log('error', sprintf(__('Failed to restore cart state: %s', 'wc-sc-debugger'), $e->getMessage()));
            error_log('WC SC Debugger: Failed to restore cart state - ' . $e->getMessage());
        }
    }

    /**
     * Clear the cart for testing
     *
     * @return void
     */
    public function clearCart(): void {
        WC()->cart->empty_cart(true);
        
        if (WC()->session) {
            WC()->session->set('wc_notices', []);
            WC()->session->set('sc_coupon_valid', null);
            WC()->session->set('sc_coupon_error', null);
            WC()->session->set('wc_sc_cart_smart_coupons', []);
        }

        $this->logger->log('info', __('Cart cleared for testing', 'wc-sc-debugger'));
    }

    /**
     * Add products to cart for testing
     *
     * @param array $product_ids Array of product IDs to add
     * @return bool True if products were added successfully
     */
    public function addProductsToCart(array $product_ids): bool {
        $productsAdded = false;
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product && $product->is_purchasable() && $product->is_in_stock()) {
                $cart_item_key = WC()->cart->add_to_cart($product_id, 1);
                if ($cart_item_key) {
                    $productsAdded = true;
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

        return $productsAdded;
    }

    /**
     * Apply a coupon to the cart
     *
     * @param string $coupon_code The coupon code to apply
     * @return bool|\WP_Error True if successful, WP_Error on failure
     */
    public function applyCoupon(string $coupon_code) {
        $this->logger->log('info', sprintf(
            __('Attempting to apply coupon: "%s"', 'wc-sc-debugger'),
            $coupon_code
        ));

        // Apply coupon with error suppression for Smart Coupons compatibility
        $result = @WC()->cart->apply_coupon($coupon_code);

        if (is_wp_error($result)) {
            $this->logger->log('error', sprintf(
                __('Failed to apply coupon: %s', 'wc-sc-debugger'),
                $result->get_error_message()
            ));
            return $result;
        } elseif (!$result) {
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
            return false;
        } else {
            WC()->cart->calculate_totals();
            $cart_total = WC()->cart->get_total('edit');
            $discount_total = WC()->cart->get_discount_total();
            
            $this->logger->log('success', __('Coupon applied successfully!', 'wc-sc-debugger'));
            $this->logger->log('success', sprintf(__('New Cart Total: %s', 'wc-sc-debugger'), wc_price($cart_total)));
            $this->logger->log('success', sprintf(__('Discount Amount: %s', 'wc-sc-debugger'), wc_price($discount_total)));
            
            return true;
        }
    }

    /**
     * Get or create a dummy product for testing
     *
     * @return int|false Product ID on success, false on failure
     */
    public function getOrCreateDummyProduct() {
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
                $this->logger->log('info', sprintf(
                    __('Created dummy product for testing (ID: %d)', 'wc-sc-debugger'),
                    $new_product_id
                ));
                return $new_product_id;
            }
        } catch (Exception $e) {
            $this->logger->log('error', sprintf(
                __('Failed to create dummy product: %s', 'wc-sc-debugger'),
                $e->getMessage()
            ));
            error_log('WC SC Debugger: Failed to create dummy product - ' . $e->getMessage());
        }

        return false;
    }
}
