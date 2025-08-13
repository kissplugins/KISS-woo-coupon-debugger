<?php
/**
 * Hook Tracker Implementation
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Hooks;

use KissPlugins\WooCouponDebugger\Interfaces\HookTrackerInterface;
use KissPlugins\WooCouponDebugger\Interfaces\LoggerInterface;

/**
 * Tracks WordPress hooks and filters during coupon debugging
 */
class HookTracker implements HookTrackerInterface {

    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Track if hooks are currently being monitored to prevent recursion
     *
     * @var bool
     */
    private $trackingActive = false;

    /**
     * Core coupon validation filters to track
     *
     * @var array
     */
    private $coreFilters = [
        'woocommerce_coupon_is_valid',
        'woocommerce_coupon_is_valid_for_product',
        'woocommerce_coupon_validate_expiry_date',
        'woocommerce_coupon_get_discount_amount',
        'woocommerce_apply_individual_use_coupon',
        'woocommerce_apply_with_individual_use_coupon',
        'woocommerce_coupon_error',
    ];

    /**
     * Smart Coupons specific filters to track
     *
     * @var array
     */
    private $smartCouponsFilters = [
        'wc_sc_validate_coupon_amount',
        'wc_sc_is_send_coupon_email',
        'wc_sc_is_coupon_restriction_available',
        'wc_sc_percent_discount_types',
        'wc_sc_coupon_type',
        'wc_sc_coupon_amount',
    ];

    /**
     * Actions to track
     *
     * @var array
     */
    private $actions = [
        'woocommerce_applied_coupon',
        'woocommerce_removed_coupon',
        'woocommerce_coupon_loaded',
        'woocommerce_before_calculate_totals',
        'woocommerce_after_calculate_totals',
    ];

    /**
     * Constructor
     *
     * @param LoggerInterface $logger Logger instance
     */
    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * Start tracking hooks and filters
     *
     * @return void
     */
    public function startTracking(): void {
        if ($this->trackingActive) {
            return; // Prevent recursion
        }
        
        $this->trackingActive = true;

        // Add filters with appropriate argument counts
        foreach ($this->coreFilters as $filter) {
            add_filter($filter, [$this, 'trackFilter'], 9999, 10);
        }

        foreach ($this->smartCouponsFilters as $filter) {
            add_filter($filter, [$this, 'trackFilter'], 9999, 10);
        }

        // Add actions
        foreach ($this->actions as $action) {
            add_action($action, [$this, 'trackAction'], 9999, 10);
        }

        $this->logger->log('info', __('Hook tracking started', 'wc-sc-debugger'));
    }

    /**
     * Stop tracking hooks and filters
     *
     * @return void
     */
    public function stopTracking(): void {
        if (!$this->trackingActive) {
            return;
        }

        $this->trackingActive = false;

        // Remove all filters
        $allFilters = array_merge($this->coreFilters, $this->smartCouponsFilters);
        foreach ($allFilters as $filter) {
            remove_filter($filter, [$this, 'trackFilter'], 9999);
        }

        // Remove all actions
        foreach ($this->actions as $action) {
            remove_action($action, [$this, 'trackAction'], 9999);
        }

        $this->logger->log('info', __('Hook tracking stopped', 'wc-sc-debugger'));
    }

    /**
     * Check if tracking is currently active
     *
     * @return bool True if tracking is active
     */
    public function isTrackingActive(): bool {
        return $this->trackingActive;
    }

    /**
     * Track a filter execution
     *
     * @param mixed $value The filtered value
     * @param mixed ...$args Additional filter arguments
     * @return mixed The original value
     */
    public function trackFilter($value, ...$args) {
        if (!$this->trackingActive) {
            return $value; // Prevent logging during restoration
        }

        $filterName = current_filter();
        $this->logger->log(
            'filter',
            sprintf(__('Filter: %s', 'wc-sc-debugger'), $filterName),
            [
                'args'   => $this->sanitizeForLogging($args),
                'return' => $this->sanitizeForLogging($value),
            ]
        );
        
        return $value;
    }

    /**
     * Track an action execution
     *
     * @param mixed ...$args Action arguments
     * @return void
     */
    public function trackAction(...$args): void {
        if (!$this->trackingActive) {
            return; // Prevent logging during restoration
        }

        $actionName = current_action();
        $this->logger->log(
            'action',
            sprintf(__('Action: %s', 'wc-sc-debugger'), $actionName),
            [
                'args' => $this->sanitizeForLogging($args),
            ]
        );
    }

    /**
     * Sanitize data for logging to prevent issues with complex objects or circular references
     *
     * @param mixed $data The data to sanitize
     * @param int   $depth The current recursion depth
     * @param array $stack Keeps track of objects to detect circular references
     * @return mixed Sanitized data
     */
    private function sanitizeForLogging($data, int $depth = 0, array &$stack = []): mixed {
        // Limit recursion depth to prevent memory exhaustion
        if ($depth > 3) {
            return '[Max Depth Reached]';
        }

        if (is_object($data)) {
            // Check for circular references
            $hash = spl_object_hash($data);
            if (isset($stack[$hash])) {
                return sprintf('[Circular Reference: %s]', get_class($data));
            }
            $stack[$hash] = true;

            if (method_exists($data, 'to_array')) {
                $result = $this->sanitizeForLogging($data->to_array(), $depth + 1, $stack);
            } elseif ($data instanceof \WC_Coupon) {
                $result = [
                    'type'          => 'WC_Coupon',
                    'id'            => $data->get_id(),
                    'code'          => $data->get_code(),
                    'amount'        => $data->get_amount(),
                    'discount_type' => $data->get_discount_type(),
                ];
            } elseif ($data instanceof \WC_Product) {
                $result = [
                    'type' => 'WC_Product',
                    'id'   => $data->get_id(),
                    'name' => $data->get_name(),
                ];
            } elseif ($data instanceof \WC_Cart) {
                $result = '[Object: WC_Cart]';
            } elseif ($data instanceof \WP_Error) {
                $result = [
                    'type'    => 'WP_Error',
                    'code'    => $data->get_error_code(),
                    'message' => $data->get_error_message(),
                ];
            } else {
                $result = sprintf('[Object: %s]', get_class($data));
            }

            unset($stack[$hash]);
            return $result;
        } elseif (is_array($data)) {
            $sanitizedArray = [];
            foreach ($data as $key => $value) {
                $sanitizedArray[$key] = $this->sanitizeForLogging($value, $depth + 1, $stack);
            }
            return $sanitizedArray;
        } elseif (is_resource($data)) {
            return '[Resource]';
        }

        return $data;
    }
}
