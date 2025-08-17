# Note
Un-resolved Crash on Activation

# KISS Woo Coupon Debugger

A companion plugin for WooCommerce Smart Coupons that helps debug coupon application issues and track hook/filter processing in real-time.

## Description

The KISS Woo Coupon Debugger is a powerful development tool designed to help WooCommerce store administrators and developers understand why coupons may not be applying correctly. It provides detailed insights into the coupon validation process, tracks all related hooks and filters, and simulates coupon application in a safe testing environment.

## Features

- **Real-time Coupon Testing**: Test any coupon code without affecting your live cart
- **Hook & Filter Tracking**: Monitor all WooCommerce and Smart Coupons hooks/filters during coupon processing
- **Variable Product Support**: Automatically handles variable products by selecting the first available variation
- **Grouped Product Support**: Automatically selects the first available child product for grouped products
- **User Simulation**: Test coupons as different users or guests to verify user-specific restrictions
- **Product Testing**: Pre-define up to 3 products for quick testing of product-specific coupon rules
- **Detailed Logging**: Comprehensive debug output showing every step of the coupon validation process
- **Memory Usage Tracking**: Monitor memory consumption during debugging operations
- **Safe Testing Environment**: All tests run in an isolated environment without affecting the actual cart

## Requirements

- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- WooCommerce Smart Coupons plugin (recommended)
- PHP 7.0 or higher

## Installation

1. Download the plugin files
2. Upload the `KISS-woo-coupon-debugger` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to WooCommerce → SC Debugger to start debugging

## Usage

### Basic Debugging

1. Go to **WooCommerce → SC Debugger**
2. Enter a coupon code you want to test
3. (Optional) Select a product to test product-specific restrictions
4. (Optional) Select a user to test user-specific restrictions
5. Click **Run Debug**
6. Review the detailed output showing all validation steps

### Setting Up Test Products

1. Go to **WooCommerce → SC Debugger Settings**
2. Enter up to 3 Product IDs that you frequently use for testing
3. The plugin will validate these products and make them available in the main debugger

### Understanding the Output

The debugger provides several types of messages:

- **Info Messages** (Blue): General information about the debugging process
- **Success Messages** (Green): Successful operations and validations
- **Warning Messages** (Yellow): Non-critical issues or important notices
- **Error Messages** (Red): Failed validations or critical issues
- **Filter Messages**: Shows when filters are applied and their return values
- **Action Messages**: Shows when actions are triggered

### Testing Different Scenarios

#### Testing Variable Products
When you select a variable product, the debugger will:
- Automatically find the first available variation
- Apply that variation to the cart
- Show which attributes were selected (e.g., "Color: Red, Size: Large")

#### Testing User Restrictions
- Select a specific user to test "new customer only" coupons
- Leave empty to test as a guest user
- The debugger will simulate the selected user during the test

#### Testing Product Restrictions
- Add specific products to test product-based coupon restrictions
- The debugger will create a test cart with your selected products
- If no products are selected, a dummy product is used for general testing

## Debugging Information Captured

- Coupon validation status
- Applied filters and their return values
- Triggered actions and their parameters
- Cart totals before and after coupon application
- Discount amounts
- Error messages and reasons for failure
- Memory usage statistics
- WooCommerce notices

## Troubleshooting

### 500 Error or White Screen
- Check PHP error logs for specific error messages
- Ensure WooCommerce is active and properly initialized
- Increase PHP memory limit if needed

### Coupon Not Applying
- Review the debug output for specific validation failures
- Check filter results to see if custom code is blocking the coupon
- Verify the coupon settings in WooCommerce

### Variable Products Not Working
- Ensure at least one variation is in stock and purchasable
- Check that variation attributes are properly set
- Review the debug log for specific variation-related messages

## Developer Information

### Hooks Tracked

The plugin monitors numerous WooCommerce and Smart Coupons hooks including:

**Core WooCommerce Filters:**
- `woocommerce_coupon_is_valid`
- `woocommerce_coupon_is_valid_for_product`
- `woocommerce_coupon_validate_expiry_date`
- `woocommerce_coupon_get_discount_amount`
- `woocommerce_apply_individual_use_coupon`
- `woocommerce_apply_with_individual_use_coupon`
- `woocommerce_coupon_error`

**Smart Coupons Filters:**
- `wc_sc_validate_coupon_amount`
- `wc_sc_is_send_coupon_email`
- `wc_sc_is_coupon_restriction_available`
- `wc_sc_percent_discount_types`
- `wc_sc_coupon_type`
- `wc_sc_coupon_amount`

**Actions:**
- `woocommerce_applied_coupon`
- `woocommerce_removed_coupon`
- `woocommerce_coupon_loaded`
- `woocommerce_before_calculate_totals`
- `woocommerce_after_calculate_totals`

### Security

- All AJAX requests are nonce-protected
- Only users with `manage_woocommerce` capability can use the debugger
- Test operations are completely isolated from the live cart
- No permanent changes are made to the database during testing

## Changelog

### 1.4.0 - 2025-08-05
- New: Added support for variable products - automatically selects first available variation
- New: Added support for grouped products - automatically selects first available child
- Enhancement: More detailed logging for product additions including variation attributes
- Enhancement: Better error messages when products cannot be added to cart

### 1.3.0 - 2025-08-04
- Fix: Properly initialize WooCommerce session for AJAX requests
- Fix: Add error boundaries and better exception handling
- Fix: Improve memory management and prevent infinite loops
- Fix: Better cart state restoration
- Add: Timeout protection for long-running operations

### 1.2.0 - 2025-08-03
- Fix: PHP fatal error by ensuring WC notices are correctly handled during the test simulation
- Tweak: Improved session handling during the coupon test

### 1.0.0
- Initial release

## Support

For support, feature requests, or bug reports, please visit:
- [GitHub Repository](https://github.com/kissplugins/KISS-woo-coupon-debugger)
- [KISS Plugins Website](https://kissplugins.com)

## License

This plugin is licensed under the GPL v3 or later.

## Credits

Developed by [KISS Plugins](https://kissplugins.com)

## Disclaimer

This plugin is intended for debugging and development purposes. Always test in a staging environment before using on a production site. The authors are not responsible for any data loss or issues that may occur from using this plugin.
