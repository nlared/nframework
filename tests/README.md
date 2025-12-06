# nframework Tests

This directory contains the test suite for the nframework project.

## Structure

```
tests/
├── bootstrap.php          # Test initialization and setup
├── Unit/                  # Unit tests for individual components
│   ├── FunctionsTest.php      # Tests for utility functions
│   ├── BaseClassTest.php      # Tests for Base and baseInput classes
│   ├── UserTest.php           # Tests for User authentication
│   └── ComponentsTest.php     # Tests for other framework components
└── Feature/               # Integration and feature tests
    └── IntegrationTest.php    # End-to-end feature tests
```

## Running Tests

### Prerequisites

1. Install PHPUnit via Composer:
```bash
cd /home/quique/Documentos/gitkraken/nframework/includes
composer install
```

### Run All Tests

From the project root directory:
```bash
./includes/vendor/bin/phpunit
```

### Run Specific Test Suites

Run only unit tests:
```bash
./includes/vendor/bin/phpunit --testsuite Unit
```

Run only feature tests:
```bash
./includes/vendor/bin/phpunit --testsuite Feature
```

### Run Specific Test Files

```bash
./includes/vendor/bin/phpunit tests/Unit/FunctionsTest.php
./includes/vendor/bin/phpunit tests/Unit/UserTest.php
```

### Run with Coverage Report (requires Xdebug)

```bash
./includes/vendor/bin/phpunit --coverage-html coverage
```

Then open `coverage/index.html` in your browser.

## Writing Tests

### Unit Test Example

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testSomething(): void
    {
        $result = someFunction();
        $this->assertEquals('expected', $result);
    }
}
```

### Testing Best Practices

1. **Test One Thing Per Test**: Each test method should test one specific behavior
2. **Use Descriptive Names**: Test names should clearly describe what they test
3. **Arrange-Act-Assert**: Structure tests in three phases:
   - Arrange: Set up test data and conditions
   - Act: Execute the code being tested
   - Assert: Verify the results
4. **Mock External Dependencies**: Use mocks for databases, APIs, and file systems
5. **Keep Tests Fast**: Unit tests should run in milliseconds

## Test Categories

### Unit Tests
- Test individual functions and classes in isolation
- Mock all external dependencies (MongoDB, file system, etc.)
- Fast execution (< 1 second total)

### Feature Tests
- Test complete workflows and user stories
- May use test database or mocked services
- Can be slower but should still complete quickly

## Current Test Coverage

### Completed Tests
- ✅ Utility functions (`functions.php`)
- ✅ Base classes (`Base`, helper functions)
- ✅ User class (permissions, data access)
- ✅ Test infrastructure and bootstrap

### Tests Needing Implementation
- ⚠️ XMLS class
- ⚠️ Table and TableF classes
- ⚠️ Router functionality
- ⚠️ File upload system
- ⚠️ Authentication flow
- ⚠️ Dialog and UI components
- ⚠️ Database operations

## MongoDB Testing

Many tests are marked as incomplete because they require MongoDB setup. To run these:

1. Set up a test MongoDB instance
2. Update `tests/bootstrap.php` with test database credentials
3. Use MongoDB test fixtures or mocking library
4. Remove `markTestIncomplete()` calls

## Continuous Integration

These tests are designed to run in CI/CD pipelines. Recommended setup:

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: mongodb
      - name: Install dependencies
        run: cd includes && composer install
      - name: Run tests
        run: ./includes/vendor/bin/phpunit
```

## Troubleshooting

### "Class not found" errors
- Ensure `tests/bootstrap.php` properly loads all required files
- Check that composer autoload is working: `composer dump-autoload`

### MongoDB connection errors
- Verify MongoDB extension is installed: `php -m | grep mongodb`
- Update `bootstrap.php` with correct test database configuration

### Permission errors
- Ensure test directories are writable
- Check that temporary files can be created in test environment

## Contributing

When adding new features to nframework:

1. Write tests first (TDD approach recommended)
2. Ensure all tests pass before committing
3. Aim for high test coverage on new code
4. Update this README if adding new test categories

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [PHPUnit Best Practices](https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html)
- [Testing MongoDB with PHP](https://www.mongodb.com/docs/drivers/php/)
