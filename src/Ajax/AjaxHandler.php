<?php
/**
 * AJAX Handler Implementation
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Ajax;

use KissPlugins\WooCouponDebugger\Interfaces\DebuggerInterface;
use KissPlugins\WooCouponDebugger\Interfaces\SettingsRepositoryInterface;
use Exception;

/**
 * Handles AJAX requests for coupon debugging
 */
class AjaxHandler {

    /**
     * Debugger instance
     *
     * @var DebuggerInterface
     */
    private $debugger;

    /** @var SettingsRepositoryInterface */
    private $settings;

    /**
     * Constructor
     *
     * @param DebuggerInterface            $debugger Debugger instance
     * @param SettingsRepositoryInterface  $settings Settings repository
     */
    public function __construct(DebuggerInterface $debugger, SettingsRepositoryInterface $settings) {
        $this->debugger = $debugger;
        $this->settings = $settings;
    }

    /**
     * Initialize AJAX hooks
     *
     * @return void
     */
    public function init(): void {
        add_action('wp_ajax_wc_sc_debug_coupon', [$this, 'handleDebugCouponAjax']);
        add_action('wp_ajax_wc_sc_clear_settings', [$this, 'handleClearSettingsAjax']);
    }

    /**
     * Handle AJAX request for debugging coupon
     *
     * @return void
     */
    public function handleDebugCouponAjax(): void {
        try {
            // Verify nonce
            check_ajax_referer('wc-sc-debug-coupon-nonce', 'security');

            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error([
                    'message' => __('You do not have permission to perform this action.', 'wc-sc-debugger')
                ]);
            }

            // Initialize WooCommerce if needed
            $this->initializeWooCommerce();

            // Get and validate input
            $input = $this->validateInput();
            if (is_wp_error($input)) {
                wp_send_json_error(['message' => $input->get_error_message()]);
            }

            // Save last used parameters
            $this->saveLastUsedParameters($input);

            // Clear previous debug messages
            $this->debugger->clearDebugMessages();

            // Test the coupon with enhanced error handling
            $result = $this->testCouponSafely(
                $input['coupon_code'],
                $input['product_ids'],
                $input['user_id'],
                !empty($input['skip_smart_coupons'])
            );

            // Prepare response
            $response = [
                'messages' => $this->debugger->getDebugMessages(),
                'coupon_valid' => !is_wp_error($result) && $result,
            ];

            if (is_wp_error($result)) {
                $response['messages'][] = [
                    'type' => 'error',
                    'message' => sprintf(
                        __('Coupon application failed: %s', 'wc-sc-debugger'),
                        $result->get_error_message()
                    ),
                    'data' => [],
                ];
            }

            wp_send_json_success($response);

        } catch (\Throwable $e) {
            error_log('WC SC Debugger AJAX Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());

            // Check if this is a Smart Coupons compatibility issue
            $is_smart_coupons_error = strpos($e->getMessage(), 'woocommerce-smart-coupons') !== false ||
                                     strpos($e->getFile(), 'woocommerce-smart-coupons') !== false;

            if ($is_smart_coupons_error) {
                wp_send_json_error([
                    'message' => __('Smart Coupons plugin compatibility issue detected. This may be due to a PHP version incompatibility. Please check if Smart Coupons is compatible with your PHP version.', 'wc-sc-debugger'),
                    'smart_coupons_error' => true,
                    'debug_info' => WP_DEBUG ? [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ] : null,
                ]);
            }

            wp_send_json_error([
                'message' => sprintf(
                    __('An error occurred: %s. Please check your server error logs for more details.', 'wc-sc-debugger'),
                    $e->getMessage()
                ),
                'debug_info' => WP_DEBUG ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => wp_debug_backtrace_summary()
                ] : null,
            ]);
        }
    }

    /**
     * Initialize WooCommerce components
     *
     * @return void
     * @throws Exception If WooCommerce cannot be initialized
     */
    private function initializeWooCommerce(): void {
        // Initialize WooCommerce if needed
        if (!did_action('woocommerce_init')) {
            WC()->init();
        }

        // Ensure session is initialized
        if (!WC()->session) {
            WC()->initialize_session();
        }

        // Force session initialization for AJAX
        if (!WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }

        // Verify WooCommerce components
        if (!function_exists('WC') ||
            !isset(WC()->cart) || !is_object(WC()->cart) ||
            !isset(WC()->session) || !is_object(WC()->session)) {
            throw new Exception(__('WooCommerce cart or session is not fully loaded. Please ensure WooCommerce is active and properly initialized.', 'wc-sc-debugger'));
        }
    }

    /**
     * Validate and sanitize AJAX input
     *
     * @return array|\WP_Error Validated input or WP_Error on failure
     */
    private function validateInput() {
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field(wp_unslash($_POST['coupon_code'])) : '';
        $product_id_selected = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
        $user_id = isset($_POST['user_id']) ? absint(wp_unslash($_POST['user_id'])) : 0;

        if (empty($coupon_code)) {
            return new \WP_Error('missing_coupon', __('Please enter a coupon code.', 'wc-sc-debugger'));
        }

        $product_ids = [];
        if ($product_id_selected > 0) {
            $product_ids = [$product_id_selected];
        }

        return [
            'coupon_code' => $coupon_code,
            'product_ids' => $product_ids,
            'user_id' => $user_id,
            'skip_smart_coupons' => isset($_POST['skip_smart_coupons'])
                ? (bool) absint(wp_unslash($_POST['skip_smart_coupons']))
                : $this->settings->getSkipSmartCoupons(),
        ];
    }

    /**
     * Test coupon with enhanced error handling for Smart Coupons compatibility
     *
     * @param string $coupon_code The coupon code to test
     * @param array  $product_ids Array of product IDs to test with
     * @param int    $user_id     User ID to simulate
     * @return bool|\WP_Error True if coupon applied successfully, WP_Error on failure
     */
    private function testCouponSafely(string $coupon_code, array $product_ids, int $user_id, bool $skipSmartCoupons) {
        // Set up error handler to catch Smart Coupons errors
        $previous_error_handler = set_error_handler(function($severity, $message, $file, $line) {
            // Check if this is a Smart Coupons related error
            if (strpos($file, 'woocommerce-smart-coupons') !== false) {
                throw new \ErrorException(
                    "Smart Coupons Error: $message",
                    0,
                    $severity,
                    $file,
                    $line
                );
            }
            // Let other errors pass through to the previous handler
            if ($previous_error_handler) {
                return call_user_func($previous_error_handler, $severity, $message, $file, $line);
            }
            return false;
        });

        try {
            // Attempt to test the coupon
            $result = $this->debugger->testCoupon($coupon_code, $product_ids, $user_id, [
                'skip_smart_coupons' => $skipSmartCoupons,
            ]);

            // Restore previous error handler
            restore_error_handler();

            return $result;

        } catch (\TypeError $e) {
            // Restore previous error handler
            restore_error_handler();

            // Detect known Smart Coupons PHP 8+ TypeError and return a friendly WP_Error
            if (strpos($e->getMessage(), 'Cannot access offset of type string on string') !== false) {
                $this->addSmartCouponsErrorMessages();
                return new \WP_Error(
                    'smart_coupons_compatibility',
                    __('Smart Coupons PHP 8+ compatibility issue. Please see debug output for details.', 'wc-sc-debugger')
                );
            }

            // Unknown TypeError; rethrow
            throw $e;

        } catch (\ErrorException $e) {
            // Restore previous error handler
            restore_error_handler();

            // Handle Smart Coupons specific errors
            if (strpos($e->getMessage(), 'Smart Coupons Error:') === 0) {
                // Log the Smart Coupons error
                error_log('WC SC Debugger: Smart Coupons compatibility issue - ' . $e->getMessage());

                // Add helpful messages to the debugger
                $this->addSmartCouponsErrorMessages();

                return new \WP_Error(
                    'smart_coupons_compatibility',
                    __('Smart Coupons plugin compatibility issue. Please check the debug output for more details.', 'wc-sc-debugger')
                );
            }

            // Re-throw other errors
            throw $e;

        } catch (\Exception $e) {
            // Restore previous error handler
            restore_error_handler();

            // Re-throw the exception to be handled by the main error handler
            throw $e;
        }
    }

    /**
     * Save last used parameters for the current user
     *
     * @param array $input Validated input parameters
     * @return void
     */
    private function saveLastUsedParameters(array $input): void {
        $params = [
            'coupon_code' => $input['coupon_code'],
            'product_id' => !empty($input['product_ids']) ? $input['product_ids'][0] : 0,
            'user_id' => $input['user_id'],
            'skip_smart_coupons' => !empty($input['skip_smart_coupons']),
        ];

        $this->settings->setLastUsedParams($params);
    }

    /**
     * Handle AJAX request for clearing all settings
     *
     * @return void
     */
    public function handleClearSettingsAjax(): void {
        try {
            // Verify nonce
            check_ajax_referer('wc-sc-debug-coupon-nonce', 'security');

            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error([
                    'message' => __('You do not have permission to perform this action.', 'wc-sc-debugger')
                ]);
            }

            // Clear last used parameters
            $this->settings->clearLastUsedParams();

            wp_send_json_success([
                'message' => __('All settings cleared successfully.', 'wc-sc-debugger')
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Error clearing settings: %s', 'wc-sc-debugger'),
                    $e->getMessage()
                )
            ]);
        }
    }

    /**
     * Add Smart Coupons error messages directly to debugger
     *
     * @return void
     */
    private function addSmartCouponsErrorMessages() {
        try {
            // Add error messages directly to the debugger's message array
            if (method_exists($this->debugger, 'getDebugMessages')) {
                // We'll manually add the messages since we can't easily access the logger
                $this->debugger->clearDebugMessages();

                // Add messages by calling the debugger's internal methods if available
                $reflection = new \ReflectionClass($this->debugger);
                if ($reflection->hasProperty('logger')) {
                    $loggerProperty = $reflection->getProperty('logger');
                    $loggerProperty->setAccessible(true);
                    $logger = $loggerProperty->getValue($this->debugger);

                    if ($logger && method_exists($logger, 'log')) {
                        $logger->log('error', __('Smart Coupons Plugin Compatibility Issue', 'wc-sc-debugger'));
                        $logger->log('warning', __('The WooCommerce Smart Coupons plugin appears to have a PHP compatibility issue.', 'wc-sc-debugger'));
                        $logger->log('info', __('This is typically caused by running Smart Coupons on PHP 8+ when it was designed for older PHP versions.', 'wc-sc-debugger'));
                        $logger->log('info', __('Recommendation: Update Smart Coupons to the latest version or contact the plugin author.', 'wc-sc-debugger'));
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore errors in logger access
            error_log('WC SC Debugger: Could not add Smart Coupons error messages - ' . $e->getMessage());
        }
    }
}
