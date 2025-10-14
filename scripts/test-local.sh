#!/bin/bash

# Local testing script for PHP Lint and WPCS
# This script mimics what the GitHub Actions will run

set -e

echo "🚀 Starting local PHP Lint and WPCS tests..."
echo "================================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed. Please install composer first."
    exit 1
fi

# Check if we're in the right directory
if [ ! -f "kiss-coupon-debugger.php" ]; then
    print_error "Please run this script from the plugin root directory."
    exit 1
fi

print_status "Installing dependencies..."
composer install --prefer-dist --no-progress

echo ""
echo "🔍 Running PHP Lint tests..."
echo "=============================="

# PHP Lint - Check syntax errors
print_status "Checking PHP syntax in all files..."
find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" | while read -r file; do
    if ! php -l "$file" > /dev/null 2>&1; then
        print_error "Syntax error in: $file"
        php -l "$file"
        exit 1
    fi
done

print_status "PHP syntax check completed successfully!"

echo ""
echo "📏 Running WordPress Coding Standards..."
echo "========================================"

# Configure PHPCS for WordPress standards
print_status "Configuring PHPCS..."
vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs
vendor/bin/phpcs --config-set default_standard WordPress

# Run PHPCS
print_status "Running PHPCS checks..."
if vendor/bin/phpcs --standard=phpcs.xml.dist --report=summary; then
    print_status "WPCS checks passed!"
else
    print_warning "WPCS issues found. Run 'vendor/bin/phpcs --standard=phpcs.xml.dist' for details."
fi

echo ""
echo "🔧 Running PHP Compatibility checks..."
echo "====================================="

# Install PHPCompatibility if not already installed
if [ ! -d "vendor/phpcompatibility/php-compatibility" ]; then
    print_status "Installing PHPCompatibility..."
    composer require --dev phpcompatibility/php-compatibility
fi

vendor/bin/phpcs --config-set installed_paths vendor/phpcompatibility/php-compatibility

print_status "Checking PHP 8.0+ compatibility..."
if vendor/bin/phpcs --standard=PHPCompatibility --runtime-set testVersion 8.0- src/ kiss-coupon-debugger.php --report=summary; then
    print_status "PHP 8.0+ compatibility checks passed!"
else
    print_warning "PHP compatibility issues found."
fi

echo ""
echo "🔍 Running PHPStan static analysis..."
echo "===================================="

if composer run-script phpstan; then
    print_status "PHPStan analysis completed!"
else
    print_warning "PHPStan found some issues."
fi

echo ""
echo "🎉 Local testing completed!"
echo "=========================="
print_status "All tests have been executed."
print_warning "Check the output above for any issues that need to be addressed."
echo ""
echo "To run individual tests:"
echo "  PHP Lint:        composer run-script lint"
echo "  WPCS:            composer run-script phpcs"
echo "  WPCS (fix):      composer run-script phpcbf"
echo "  PHP Compat:      composer run-script php-compat"
echo "  PHPStan:         composer run-script phpstan"
echo "  All Tests:       composer run-script test-all"
