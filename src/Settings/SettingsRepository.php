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
}

