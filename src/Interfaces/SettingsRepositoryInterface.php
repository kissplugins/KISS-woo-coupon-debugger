<?php
/**
 * Settings Repository Interface
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Interfaces;

interface SettingsRepositoryInterface {
    /**
     * Whether to skip Smart Coupons stack and simulate results
     */
    public function getSkipSmartCoupons(): bool;

    /**
     * Set skip Smart Coupons flag
     */
    public function setSkipSmartCoupons(bool $enabled): void;

    /**
     * Get validated products array
     * @return array
     */
    public function getValidatedProducts(): array;

    /**
     * Set validated products
     * @param array $products
     */
    public function setValidatedProducts(array $products): void;

    /**
     * Get last used parameters for the current user
     * @return array
     */
    public function getLastUsedParams(): array;

    /**
     * Set last used parameters for the current user
     * @param array $params
     */
    public function setLastUsedParams(array $params): void;

    /**
     * Clear last used parameters for the current user
     */
    public function clearLastUsedParams(): void;
}

