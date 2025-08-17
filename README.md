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

## Continuous Integration (CI)

We run automated checks on every push/PR using GitHub Actions:
- PHP syntax linting (php -l)
- Static analysis with PHPStan
- WordPress coding standards via PHPCS (WPCS)

See CI.md for details on what runs, how to run these checks locally, and tips for interpreting results.


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
See Changelog.md

## Special Overview for v2.1.0+ Features

Version 2.1.0 introduces powerful new features that dramatically improve the user experience and collaboration capabilities of the WC SC Debugger plugin. These features focus on **workflow efficiency**, **team collaboration**, and **enhanced troubleshooting**.

---

## 🔗 URL Sharing & Parameter Persistence

### **Generate Shareable URLs**
Create URLs that contain all your current test parameters, perfect for:
- **Team Collaboration**: Share exact test configurations with colleagues
- **Bug Reports**: Include precise reproduction steps in support tickets
- **Bookmarking**: Save frequently used test scenarios
- **Documentation**: Include working examples in internal docs

**How to Use:**
1. Fill in your coupon code, product, user, and settings
2. Click **"Generate Shareable URL"**
3. Copy the generated URL
4. Share with team members or bookmark for later

**Example Generated URL:**
```
https://yoursite.com/wp-admin/admin.php?page=wc-sc-debugger&coupon_code=SAVE20&product_id=123&user_id=456&skip_smart_coupons=1
```

### **Automatic Parameter Persistence**
Never lose your test settings again! The plugin now:
- **Remembers Your Last Settings**: Automatically saves your last used parameters
- **Restores on Page Load**: Pre-fills forms with your previous values
- **User-Specific**: Each team member has their own saved preferences
- **Smart Loading**: URL parameters take priority over saved settings

**Benefits:**
- ⚡ **Faster Testing**: No need to re-enter the same parameters repeatedly
- 🎯 **Consistent Testing**: Easily return to your preferred test configuration
- 👥 **Team Efficiency**: Each user maintains their own testing preferences

### **Clear All Settings**
One-click reset functionality:
- **Complete Reset**: Clears all form fields instantly
- **Clears Saved Data**: Removes your stored preferences
- **Confirmation Dialog**: Prevents accidental clearing
- **Fresh Start**: Perfect for switching between different test scenarios

---

## 🐛 Enhanced Debug System

### **Smart Debug Logging**
Comprehensive troubleshooting system that helps diagnose issues:

**JavaScript Console Logging:**
- Script loading verification
- Element availability checks
- Form interaction tracking
- AJAX request/response monitoring
- User selection loading status

**PHP Error Log Integration:**
- Script enqueuing process
- Parameter handling details
- Database operation results
- AJAX handler execution
- Settings save/load operations

### **Flexible Debug Control**
Multiple ways to enable debug mode based on your needs:

**1. Plugin Settings (Recommended)**
- Go to **WooCommerce > SC Debugger Settings**
- Check **"Enable Debug Logging"**
- Perfect for ongoing troubleshooting

**2. URL Parameter (Temporary)**
- Add `?wc_sc_debug=1` to any debugger page
- Great for quick testing without changing settings

**3. WordPress Debug Mode (Automatic)**
- Automatically enables when `WP_DEBUG` is true
- Integrates with your existing WordPress debug setup

### **Production-Safe Design**
- **Zero Overhead**: No performance impact when debug mode is disabled
- **Smart Logging**: Only processes debug messages when actually needed
- **Critical Errors**: Always logs important errors regardless of debug mode
- **Easy Toggle**: Can be quickly enabled/disabled as needed

---

## 🎯 Use Cases & Workflows

### **For Development Teams**
1. **Bug Reproduction**: Generate URLs with exact parameters that reproduce issues
2. **Code Reviews**: Share test configurations for peer validation
3. **QA Testing**: Standardize test scenarios across team members
4. **Documentation**: Include working examples in technical documentation

### **For Support Teams**
1. **Customer Issues**: Ask customers to share their debugger URL
2. **Troubleshooting**: Enable debug mode to diagnose problems
3. **Knowledge Base**: Create shareable test scenarios for common issues
4. **Training**: Use saved configurations for team training sessions

### **For Site Administrators**
1. **Regular Testing**: Bookmark frequently used test configurations
2. **Performance Monitoring**: Use debug mode to identify bottlenecks
3. **Environment Comparison**: Compare behavior between staging and production
4. **Maintenance**: Quick access to common debugging scenarios

---

## 🔧 Technical Implementation

### **Data Storage**
- **User Preferences**: Stored in WordPress user meta (per-user basis)
- **URL Parameters**: Standard GET parameters for easy sharing
- **Settings**: WordPress options API for global configuration
- **Security**: Proper nonce verification and capability checks

### **Performance Considerations**
- **Lazy Loading**: Debug features only load when needed
- **Minimal Overhead**: Less than 1% performance impact when enabled
- **Production Ready**: Safe to deploy with debug mode disabled
- **Efficient Storage**: Minimal database footprint

### **Browser Compatibility**
- **Modern Browsers**: Full feature support in Chrome, Firefox, Safari, Edge
- **Mobile Responsive**: Works on tablets and mobile devices
- **Graceful Degradation**: Core functionality works even with JavaScript disabled
- **Accessibility**: Proper ARIA labels and keyboard navigation

---

## 📋 Migration & Upgrade Notes

### **Automatic Upgrade**
- **Seamless Migration**: Existing functionality remains unchanged
- **New Features**: Available immediately after upgrade
- **No Configuration Required**: Works out of the box
- **Backward Compatible**: All existing workflows continue to work

### **Recommended Actions After Upgrade**
1. **Test New Features**: Try generating a shareable URL
2. **Configure Debug Mode**: Set your preferred debug logging level
3. **Train Team Members**: Share the new collaboration features
4. **Update Documentation**: Include new URL sharing capabilities

---

## 🆘 Troubleshooting

### **If URL Sharing Doesn't Work**
1. Check browser console for JavaScript errors
2. Verify all form fields are properly filled
3. Ensure you have proper WordPress permissions
4. Try enabling debug mode for detailed logging

### **If Parameters Don't Persist**
1. Verify you're logged into WordPress
2. Check that user meta storage is working
3. Ensure database permissions are correct
4. Try clearing browser cache

### **For Debug Logging Issues**
1. Verify debug mode is enabled in settings
2. Check WordPress error log location
3. Ensure `WP_DEBUG_LOG` is enabled
4. Verify file permissions on log directory

---

## 🎉 Getting Started

1. **Update to v2.1.0**: Install the latest version
2. **Try URL Sharing**: Fill in a test scenario and generate a URL
3. **Test Parameter Persistence**: Reload the page and see your settings restored
4. **Enable Debug Mode**: Turn on logging for detailed troubleshooting
5. **Share with Team**: Start collaborating with shareable URLs

**Need Help?** Check the `DEBUG-SYSTEM.md` file for detailed troubleshooting information.

## Disclaimer
This plugin is intended for debugging and development purposes. Always test in a staging environment before using on a production site. The authors are not responsible for any data loss or issues that may occur from using this plugin.