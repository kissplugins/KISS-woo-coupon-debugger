# Continuous Integration Setup

## Overview

This document describes the CI/CD setup for the KISS Woo Coupon Debugger plugin, including PHP Lint, WordPress Coding Standards (WPCS), and PHP compatibility testing.

## GitHub Actions Workflow

### Workflow File: `.github/workflows/php-lint-and-wpcs.yml`

The workflow runs automatically on:
- **Push** to `main`, `develop`, or `master` branches
- **Pull Requests** targeting these branches  
- **Merge Groups** (when using GitHub's merge queue)

### Jobs Overview

#### 1. **PHP Lint** (`php-lint`)
- **Purpose**: Check PHP syntax errors across multiple PHP versions
- **PHP Versions Tested**: 8.0, 8.1, 8.2, 8.3
- **What it does**:
  - Validates `composer.json` and `composer.lock`
  - Runs `php -l` on all PHP files
  - Tests main plugin file and source directory
  - Uses matrix strategy for parallel testing across PHP versions

#### 2. **WordPress Coding Standards** (`wpcs`)
- **Purpose**: Ensure code follows WordPress coding standards
- **PHP Version**: 8.2 (latest stable)
- **What it does**:
  - Installs WPCS via Composer
  - Configures PHPCS with WordPress standards
  - Runs PHPCS with `cs2pr` for GitHub annotations
  - Provides summary report on failures

#### 3. **PHP Compatibility** (`php-compatibility`)
- **Purpose**: Check compatibility with PHP 8.0+
- **PHP Version**: 8.2 for testing
- **What it does**:
  - Installs PHPCompatibility standard
  - Tests code against PHP 8.0+ requirements
  - Scans both source directory and main plugin file

#### 4. **PHPStan Static Analysis** (`phpstan`)
- **Purpose**: Static code analysis for type safety and bugs
- **PHP Version**: 8.2
- **What it does**:
  - Runs PHPStan at level 8 (strictest)
  - Uses custom configuration for WordPress compatibility
  - Analyzes source code for potential issues

## Configuration Files

### 1. **phpcs.xml.dist**
WordPress Coding Standards configuration:
```xml
<ruleset name="KISS Woo Coupon Debugger">
  <file>src</file>
  <rule ref="WordPress" />
  <config name="testVersion" value="8.0-" />
</ruleset>
```

### 2. **phpstan.neon**
PHPStan configuration with WordPress-specific ignores:
- Level 8 analysis (strictest)
- WordPress function/constant ignores
- WooCommerce compatibility
- Excludes vendor and test directories

### 3. **composer.json**
Updated requirements:
- **PHP**: `>=8.0` (updated from 7.4)
- **Dev Dependencies**: WPCS, PHPStan, PHPCompatibility

## Local Testing

### Quick Test Script
Run the local test script to verify everything works:
```bash
./scripts/test-local.sh
```

### Manual Testing Commands

**PHP Lint:**
```bash
find . -name "*.php" -not -path "./vendor/*" | xargs php -l
```

**WordPress Coding Standards:**
```bash
vendor/bin/phpcs --standard=phpcs.xml.dist
```

**PHP 8.0+ Compatibility:**
```bash
vendor/bin/phpcs --standard=PHPCompatibility --runtime-set testVersion 8.0- src/
```

**PHPStan Analysis:**
```bash
composer run-script phpstan
```

## PHP Version Requirements

### Updated from PHP 7.4+ to PHP 8.0+

**Files Updated:**
- `composer.json`: `"php": ">=8.0"`
- `kiss-coupon-debugger.php`: `Requires PHP: 8.0`
- `phpcs.xml.dist`: `testVersion: 8.0-`

**Rationale:**
- PHP 7.4 reached end-of-life in November 2022
- PHP 8.0+ provides better performance and modern features
- Aligns with WordPress and WooCommerce recommendations
- Enables use of modern PHP features (union types, attributes, etc.)

## Workflow Triggers

### Automatic Triggers
- **Push to main/develop/master**: Full test suite
- **Pull Requests**: Full test suite with annotations
- **Merge Groups**: Pre-merge validation

### Manual Triggers
- Workflow can be manually triggered from GitHub Actions tab
- Individual jobs can be re-run if needed

## Error Handling and Reporting

### GitHub Annotations
- PHPCS errors appear as inline comments in PRs
- PHP lint errors show exact file and line numbers
- PHPStan issues are highlighted in the diff

### Failure Modes
- **PHP Lint**: Fails on any syntax error
- **WPCS**: Fails on coding standard violations
- **PHP Compatibility**: Fails on incompatible code
- **PHPStan**: Fails on type errors or bugs

### Caching
- Composer packages are cached per PHP version
- Reduces workflow execution time
- Cache keys include `composer.lock` hash for accuracy

## Best Practices

### Before Committing
1. Run `./scripts/test-local.sh` locally
2. Fix any reported issues
3. Ensure all tests pass before pushing

### Code Quality
- Follow WordPress coding standards
- Use proper type hints (PHP 8.0+ features)
- Write PHPDoc comments for all methods
- Avoid deprecated PHP features

### Performance
- Workflows run in parallel where possible
- Matrix strategy for PHP version testing
- Efficient caching to reduce execution time

## Troubleshooting

### Common Issues

**Composer Install Fails:**
- Check `composer.json` syntax
- Verify all dependencies are available
- Clear composer cache if needed

**PHPCS Errors:**
- Run `vendor/bin/phpcbf` to auto-fix simple issues
- Check WordPress coding standards documentation
- Use `--report=source` for detailed error locations

**PHPStan Errors:**
- Check `phpstan.neon` configuration
- Add specific ignores for WordPress/WooCommerce functions
- Verify type hints are correct

**PHP Compatibility Issues:**
- Check for deprecated functions/features
- Use PHP 8.0+ compatible syntax
- Test with multiple PHP versions locally

## Future Enhancements

### Potential Additions
- **Unit Tests**: PHPUnit test suite
- **Integration Tests**: WordPress/WooCommerce integration
- **Security Scanning**: PHPMD, Psalm, or similar
- **Performance Testing**: Benchmarking critical paths
- **Deployment**: Automated releases to WordPress.org

### Monitoring
- Track workflow success rates
- Monitor execution times
- Review and update PHP version matrix as new versions are released
