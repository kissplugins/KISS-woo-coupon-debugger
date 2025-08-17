<?php
/**
 * Settings Repository
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Settings;

use KissPlugins\WooCouponDebugger\Interfaces\SettingsRepositoryInterface;

class SettingsRepository implements SettingsRepositoryInterface {
    private const OPT_SKIP_SC = 'wc_sc_debugger_skip_smart_coupons';
    private const OPT_VALIDATED_PRODUCTS = 'wc_sc_debugger_validated_products';
    private const OPT_LAST_USED_PARAMS = 'wc_sc_debugger_last_used_params';

    public function getSkipSmartCoupons(): bool {
        return (bool) get_option(self::OPT_SKIP_SC, 0);
    }

    public function setSkipSmartCoupons(bool $enabled): void {
        update_option(self::OPT_SKIP_SC, $enabled ? 1 : 0);
    }

    public function getValidatedProducts(): array {
        $val = get_option(self::OPT_VALIDATED_PRODUCTS, []);
        return is_array($val) ? $val : [];
    }

    public function setValidatedProducts(array $products): void {
        update_option(self::OPT_VALIDATED_PRODUCTS, $products);
    }

    /**
     * Get last used parameters for the current user
     *
     * @return array
     */
    public function getLastUsedParams(): array {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return [];
        }

        $params = get_user_meta($user_id, self::OPT_LAST_USED_PARAMS, true);
        return is_array($params) ? $params : [];
    }

    /**
     * Set last used parameters for the current user
     *
     * @param array $params
     * @return void
     */
    public function setLastUsedParams(array $params): void {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Sanitize parameters
        $sanitized_params = [
            'coupon_code' => isset($params['coupon_code']) ? sanitize_text_field($params['coupon_code']) : '',
            'product_id' => isset($params['product_id']) ? absint($params['product_id']) : 0,
            'user_id' => isset($params['user_id']) ? absint($params['user_id']) : 0,
            'skip_smart_coupons' => isset($params['skip_smart_coupons']) ? (bool) $params['skip_smart_coupons'] : false,
        ];

        update_user_meta($user_id, self::OPT_LAST_USED_PARAMS, $sanitized_params);
    }

    /**
     * Clear last used parameters for the current user
     *
     * @return void
     */
    public function clearLastUsedParams(): void {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        delete_user_meta($user_id, self::OPT_LAST_USED_PARAMS);
    }
}

