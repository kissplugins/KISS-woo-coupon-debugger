# Changelog

All notable changes to the KISS Woo Coupon Debugger plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-08-13

### Phase 2 (1.5b): Smart Coupons Resilience and Simulation
- Added setting: "Skip Smart Coupons stack (simulate)" to avoid PHP 8+ fatals originating from Smart Coupons
- Heuristic simulation mode estimates discount and continues validating other constraints when Smart Coupons throws TypeError
- Hardened AJAX and core error handling to catch Throwable and TypeError without 500s
- Improved debug messages to clearly call out Smart Coupons compatibility issues
- Major refactor completed; bumping to v2.0.0 series. v1.x kept as legacy non-PSR-4 branch (v1.x-stable)

## [1.4.0] - 2025-08-13

### 🚀 Major Architecture Refactoring (Phase 1 Priority 1)

#### Added
- **PSR-4 Autoloading**: Implemented modern autoloading with `KissPlugins\WooCouponDebugger` namespace
- **Dependency Injection Container**: Added lightweight DI container with singleton support and automatic dependency resolution
- **Core Interfaces**: Created extensible interface contracts:
  - `DebuggerInterface` - Main debugging functionality
  - `LoggerInterface` - Debug message logging
  - `HookTrackerInterface` - WordPress hook monitoring
  - `CartSimulatorInterface` - Cart simulation
  - `ContainerInterface` - Dependency injection
- **Composer Support**: Added `composer.json` with development dependencies and scripts
- **Modern PHP Requirements**: Updated to require PHP 7.4+ with type hints throughout

#### Changed
- **Modular Architecture**: Split monolithic 947-line class into focused components:
  - `DebuggerCore` - Core debugging logic
  - `AdminInterface` - WordPress admin integration
  - `AjaxHandler` - AJAX request processing
  - `HookTracker` - Hook/filter monitoring
  - `CartSimulator` - Cart simulation
  - `Logger` - Debug message logging
- **Improved Error Handling**: Enhanced exception handling and graceful degradation
- **Type Safety**: Added comprehensive type hints for better code reliability
- **Documentation**: Complete PHPDoc comments for all methods and properties

#### Technical Improvements
- **Separation of Concerns**: Each class has single, well-defined responsibility
- **Loose Coupling**: Components communicate through interfaces for better testability
- **Memory Optimization**: Better object lifecycle management and singleton patterns
- **Performance**: Lazy loading and optimized autoloading
- **Extensibility**: Plugin-ready architecture for third-party extensions

#### Developer Experience
- **Code Quality Tools**: Added PHPStan, PHPCS, PHPMD support in composer.json
- **Testing Ready**: Architecture prepared for PHPUnit integration
- **Modern Standards**: PSR-4 compliant code organization
- **Maintainability**: Reduced complexity and improved code readability

### 🔧 Backward Compatibility
- **Full Compatibility**: All existing functionality preserved
- **WordPress Integration**: Maintained existing admin menu structure
- **WooCommerce Support**: Continued support for all WooCommerce versions
- **Settings Preservation**: Existing plugin settings remain intact

## [1.3.0] - 2025-08-04

### Fixed
- Properly initialize WooCommerce session for AJAX requests
- Add error boundaries and better exception handling
- Improve memory management and prevent infinite loops
- Better cart state restoration
- Add timeout protection for long-running operations

## [1.2.0] - 2025-08-03

### Fixed
- PHP fatal error by ensuring WC notices are correctly handled during test simulation
- Improved session handling during coupon test

## [1.0.0] - 2025-08-03

### Added
- Initial release of KISS Woo Coupon Debugger
- Real-time coupon testing without affecting live cart
- Hook & filter tracking for WooCommerce and Smart Coupons
- User simulation for testing user-specific restrictions
- Product testing with pre-defined products
- Detailed logging with comprehensive debug output
- Memory usage tracking
- Safe testing environment with state restoration
- Admin interface with intuitive workflow
- AJAX-powered debugging without page refreshes

### Features
- **Coupon Testing**: Test any coupon code in isolated environment
- **Hook Monitoring**: Track 13+ critical WooCommerce hooks and filters
- **User Simulation**: Test as different users or guests
- **Product Integration**: Support for variable and grouped products
- **Debug Output**: Categorized messages with collapsible sections
- **Performance Tracking**: Memory usage and execution time monitoring
- **Security**: Nonce verification and capability checks
- **Internationalization**: Translation-ready with proper text domains

---

## Upgrade Notes

### Upgrading to 1.4.0
- **PHP Version**: Ensure your server runs PHP 7.4 or higher
- **Composer**: Run `composer install` if using development tools
- **No Action Required**: Plugin automatically uses new architecture
- **Settings Preserved**: All existing settings and configurations remain intact

### Development Setup (Optional)
```bash
# Install development dependencies
composer install

# Run code quality checks
composer run phpstan
composer run phpcs
composer run phpmd

# Run tests (when available)
composer run test
```

---

## Support & Contributing

- **GitHub**: [KISS Woo Coupon Debugger Repository](https://github.com/kissplugins/KISS-woo-coupon-debugger)
- **Website**: [KISS Plugins](https://kissplugins.com)
- **Issues**: Report bugs and request features on GitHub
- **Documentation**: See README.md for detailed usage instructions

---

*This changelog follows the [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format for clear, organized release notes.*
