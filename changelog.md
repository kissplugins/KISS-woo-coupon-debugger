# Changelog

#### 1.2.6 - 2025-08-03
- Tweak: Added deep logging for `woocommerce_add_to_cart_validation` filter and any generated WC notices to better capture plugin conflicts.

#### 1.2.5 - 2025-08-03
- Feature: Added an environment versions panel to the top of the debugger page.

#### 1.2.4 - 2025-08-03
- Feature: Added the plugin version number to admin page titles.

#### 1.2.3 - 2025-08-03
- Tweak: Added explicit logging for the success or failure of the `add_to_cart` function.

#### 1.2.2 - 2025-08-03
- Tweak: Added deep logging for cart contents and product prices to debug zero-total issue.

#### 1.2.1 - 2025-08-03
- Tweak: Added more detailed logging during coupon test to show pre-coupon total, coupon details, and discount amount.

#### 1.2.0 - 2025-08-03
- Fix: PHP fatal error by ensuring WC notices are correctly handled during the test simulation.
- Tweak: Improved session handling during the coupon test.