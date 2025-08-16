# KISS Woo Coupon Debugger - Development Roadmap

This document outlines planned features and improvements for future versions of the KISS Woo Coupon Debugger plugin.

## Claude code fixes for non PSR4 v1.x series.

Looking at this WooCommerce coupon debugger plugin, I've identified 3 critical issues that should be addressed for security and performance:

## 1. ✅ Unbounded Memory Usage and Potential DoS (Fixed)

Location: log_message() in kiss-coupon-debugger.php

- Implemented a hard cap at 1000 messages; new logs are dropped once the limit is reached
- Truncate message strings to 1000 characters
- Cap data payload size at ~10KB (fallback to small sentinel)

## 2. ✅ Infinite Recursion in Object Sanitization (Fixed)

Location: sanitize_for_logging() function

- Reduced max traversal depth to 2
- Fixed circular reference detection by keeping visited objects in the stack (no unset)
- Added large array guard (returns a summary when count > 100)

## 3. **Inadequate AJAX Input Validation** (Medium-High)

**Location**: `handle_debug_coupon_ajax()` function

**Issue**: Minimal validation on user inputs that control plugin behavior:

```php
$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
$product_id_selected = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
```

**Problems**:
- No length limits on coupon code (could be used for resource exhaustion)
- No validation that user_id exists or is accessible to current user
- No rate limiting on debug requests
- Product ID validation happens later, allowing invalid IDs to enter processing

**Fix**:
```php
// Validate coupon code
$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
if ( strlen( $coupon_code ) > 50 ) { // Reasonable coupon code limit
    wp_send_json_error( array( 'message' => __( 'Coupon code too long.', 'wc-sc-debugger' ) ) );
}

// Validate product ID exists if provided
$product_id_selected = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
if ( $product_id_selected > 0 && ! wc_get_product( $product_id_selected ) ) {
    wp_send_json_error( array( 'message' => __( 'Invalid product ID.', 'wc-sc-debugger' ) ) );
}

// Validate user ID exists if provided  
$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
if ( $user_id > 0 && ! get_user_by( 'id', $user_id ) ) {
    wp_send_json_error( array( 'message' => __( 'Invalid user ID.', 'wc-sc-debugger' ) ) );
}

// Add simple rate limiting
$rate_limit_key = 'wc_sc_debug_' . get_current_user_id();
$recent_requests = get_transient( $rate_limit_key );
if ( $recent_requests && $recent_requests > 10 ) { // 10 requests per minute
    wp_send_json_error( array( 'message' => __( 'Too many debug requests. Please wait.', 'wc-sc-debugger' ) ) );
}
set_transient( $rate_limit_key, ( $recent_requests ?: 0 ) + 1, 60 );
```

## Summary

While this plugin operates in a trusted admin environment, these issues could still cause:
- **Server crashes** from memory exhaustion
- **Performance degradation** from infinite loops
- **Resource abuse** from malicious or accidental overuse

The fixes focus on adding proper bounds checking, preventing infinite recursion, and validating inputs before processing.


## Enhanced Testing Capabilities

### Bulk Testing Mode
- **Feature**: Test multiple coupons at once
- **Details**: 
  - Allow users to input multiple coupon codes (comma-separated or one per line)
  - Generate comparative report showing which coupons work with which products
  - Export results to CSV for documentation

### Cart Simulation Presets
- **Feature**: Save and load cart configurations
- **Details**:
  - Save frequently used cart combinations (products + quantities)
  - Name and organize presets for different testing scenarios
  - Quick-load presets for rapid testing

### Advanced Product Selection
- **Feature**: Enhanced product picker with search and filters
- **Details**:
  - AJAX-powered product search (like customer search)
  - Filter by category, tag, or product type
  - Multi-select capability for testing multiple products simultaneously
  - Support for specific variation selection (not just first available)

## Debugging Intelligence

### Smart Diagnosis
- **Feature**: Intelligent issue detection
- **Details**:
  - Analyze debug output and suggest common solutions
  - Detect conflicting plugins/themes affecting coupons
  - Provide actionable recommendations for fixing issues

### Performance Profiling
- **Feature**: Detailed performance metrics
- **Details**:
  - Track execution time for each hook/filter
  - Identify slow-performing customizations
  - Generate performance reports with optimization suggestions

### Visual Hook Flow
- **Feature**: Interactive visualization of hook execution
- **Details**:
  - Flowchart showing the path of coupon validation
  - Highlight where validation fails
  - Click on nodes to see detailed information
  - Export as image for documentation

## Integration & Compatibility

### Third-Party Plugin Support
- **Feature**: Extended compatibility with popular coupon/discount plugins
- **Details**:
  - Support for Advanced Coupons for WooCommerce
  - Support for YITH WooCommerce Gift Cards
  - Support for WooCommerce Points and Rewards
  - Detect and debug custom coupon types

### REST API Endpoint
- **Feature**: Programmatic debugging capabilities
- **Details**:
  - REST API endpoint for automated testing
  - Useful for CI/CD pipelines
  - Batch testing via API calls
  - JSON responses for easy integration

### WooCommerce Subscriptions Integration
- **Feature**: Debug subscription-specific coupon issues
- **Details**:
  - Test recurring coupon applications
  - Debug sign-up fee discounts
  - Validate renewal discounts

## User Experience Enhancements

### Real-time Debugging
- **Feature**: Live debugging without page refresh
- **Details**:
  - WebSocket or Server-Sent Events for real-time updates
  - Debug coupons as customers use them
  - Live notification system for debugging events

### Debug History
- **Feature**: Historical debugging data
- **Details**:
  - Store debugging sessions with timestamps
  - Search and filter previous debug sessions
  - Compare results between different sessions
  - Automatic cleanup of old sessions

### Enhanced UI/UX
- **Feature**: Modern, intuitive interface
- **Details**:
  - React-based admin interface
  - Dark mode support
  - Collapsible debug sections
  - Better mobile responsiveness
  - Keyboard shortcuts for common actions

## Enterprise Features

### Multi-site Support
- **Feature**: Network-wide debugging capabilities
- **Details**:
  - Debug coupons across all sites in a multisite network
  - Centralized debugging dashboard
  - Cross-site coupon validation testing

### Role-Based Access
- **Feature**: Granular permissions system
- **Details**:
  - Create custom debugging roles
  - Limit access to specific debugging features
  - Audit trail of who debugged what

### Export & Reporting
- **Feature**: Comprehensive reporting system
- **Details**:
  - Generate PDF reports of debugging sessions
  - Schedule automated coupon validation tests
  - Email reports to stakeholders
  - Integration with popular analytics tools

### Staging/Production Sync
- **Feature**: Test coupons across environments
- **Details**:
  - Sync coupon configurations between staging and production
  - Test production coupons in staging environment
  - Environment-specific debugging profiles

## Future Considerations

### Machine Learning Integration
- Predict coupon validation issues before they occur
- Suggest optimal coupon configurations
- Anomaly detection for unusual coupon behavior

### Blockchain Integration
- Immutable audit trail of coupon usage
- Decentralized coupon validation for multi-vendor marketplaces

### Advanced Security Features
- Detect potential coupon fraud attempts
- Rate limiting analysis
- Geographic restriction testing
- VPN/proxy detection simulation

### Accessibility Improvements
- Full WCAG 2.1 AA compliance
- Screen reader optimizations
- Voice-controlled debugging commands

### Internationalization
- Full translation support
- RTL language support
- Locale-specific coupon testing

## Community Requested Features

We're always listening to our users! Here are features requested by the community that we're considering:

1. **Coupon conflict detector** - Identify which coupons conflict with each other
2. **Time-based testing** - Simulate different dates/times for testing scheduled coupons
3. **Customer segment testing** - Test coupons against different customer groups
4. **Mobile app** - iOS/Android app for debugging on the go
5. **Slack/Discord integration** - Get debugging notifications in team chat
6. **Video tutorials** - Built-in video guides for complex debugging scenarios

## Contributing

We welcome contributions! If you have ideas for features not listed here, please:

1. Open an issue on our [GitHub repository](https://github.com/kissplugins/KISS-woo-coupon-debugger)
2. Submit a pull request with your implementation
3. Join our community discussions

## Voting for Features

Want to influence our development priorities? Vote for your most wanted features:
- 👍 React to issues on GitHub
- 📧 Email us at devops@kissplugins.com
- 🗳️ Participate in our quarterly feature surveys

---

Last updated: August 2025
