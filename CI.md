## Continuous Integration (CI) Guide

This project uses GitHub Actions to automatically check code quality on every push and pull request. The pipeline provides fast feedback on syntax errors, static analysis issues, and WordPress coding standard violations.

### What runs in CI

Workflow file: .github/workflows/php-lint.yml

- Triggers
  - push: any branch
  - pull_request: any branch
- Matrix: PHP 7.4 and 8.1
- Steps
  - Checkout
  - Setup PHP (shivammathur/setup-php)
  - composer validate
  - composer install (with dev dependencies)
  - PHP syntax lint: php -l for all tracked .php files (skips vendor and node_modules)
  - PHPStan static analysis: composer phpstan
  - PHPCS coding standards (WordPress): composer phpcs

### Why this helps

- Catches syntax errors early (php -l)
- Keeps codebase consistent and readable (PHPCS + WordPress standard)
- Prevents subtle bugs (PHPStan static analysis)
- Validates composer.json integrity (composer validate)
- Ensures compatibility with multiple PHP versions (matrix)

### Repository prerequisites

composer.json (require-dev) includes:
- phpstan/phpstan
- squizlabs/php_codesniffer
- wp-coding-standards/wpcs
- dealerdirect/phpcodesniffer-composer-installer
- phpmd/phpmd (optional, not enabled by default in CI)

Composer scripts:
- "phpstan": "phpstan analyse src --level=8"
- "phpcs": "phpcs src --standard=WordPress"
- "test": "phpunit" (tests can be added later)

PHPCS configuration:
- phpcs.xml.dist defines paths to scan (src/) and common exclusions

### Running checks locally

1) Install dependencies
- composer install

2) Syntax lint
- Find errors quickly by running php -l per file or use a loop

3) Static analysis
- composer phpstan

4) Coding standards (WordPress)
- composer phpcs
- Optional auto-fix for many issues: vendor/bin/phpcbf --standard=WordPress src

5) Unit tests (future)
- composer test

### Interpreting CI results

- On a PR, open the Checks tab to see each job (per PHP version)
- Click into a failing step to view logs
- Use “Re-run jobs” after pushing fixes or if a transient failure occurred

Typical outputs:
- php -l: shows the file and line number of a syntax error
- PHPStan: lists errors with file:line and a description; increase specificity or add guards to satisfy types
- PHPCS: reports standard violations with file:line and a code (e.g., WordPress.NamingConventions)

### Common issues and fixes

- “WordPress standard not found” in PHPCS
  - Ensure composer install ran successfully (dev deps present)
  - Confirm wp-coding-standards/wpcs and dealerdirect/phpcodesniffer-composer-installer are in require-dev

- High volume of PHPCS findings
  - Prioritize errors; consider running phpcbf to auto-fix format-only issues
  - You can add temporary exclusions in phpcs.xml.dist, but prefer code fixes where possible

- PHPStan level too strict
  - Start by fixing obvious issues (null checks, type hints)
  - As a last resort, lower level or add baseline/ignores, but keep it minimal

### Extending the workflow

- Add unit tests
  - Uncomment/add a PHPUnit step: run: composer test
  - Provide phpunit.xml.dist and a test bootstrap (tests/)

- Add caching for speed
  - Use actions/cache to cache Composer’s vendor directory and PHPStan cache

- Add more PHP versions
  - Extend the matrix (e.g., 8.2)

- Add PHP Mess Detector (PHPMD)
  - Add a step: composer phpmd

### FAQ

- Where are workflow logs?
  - GitHub → Actions → select a run → click a job → see step logs

- Does PHPCS lint only src/?
  - Yes, per phpcs.xml.dist. Adjust as needed.

- How do I update rules?
  - Edit phpcs.xml.dist or composer scripts; commit and push

- How do I run only one tool locally?
  - composer phpstan or composer phpcs as needed

---
If you have any questions or want to propose CI improvements (e.g., release packaging, artifact builds, or integration tests), open an issue or PR.

