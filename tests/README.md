# Test Suite

This directory contains all test scripts for the DBV Bridge application.

## Directory Structure

```
tests/
├── api/           # API endpoint tests
├── unit/         # Unit tests for classes/services
├── integration/  # Integration tests
└── README.md     # This file
```

## Running Tests

### Web-Based Tests (API)

1. **API Endpoint Tests:**
   ```
   http://localhost/dbnew/tests/api/test_endpoints.php
   ```

2. **Withdrawal Endpoint Tests:**
   ```
   http://localhost/dbnew/tests/api/test_withdraw_endpoint.php
   ```

### Command Line Tests

Run the test runner:
```bash
php tests/run_tests.php
```

## Test Files

### API Tests
- `test_endpoints.php` - Tests all API endpoints for accessibility
- `test_withdraw_endpoint.php` - Specific tests for withdrawal endpoint

### Unit Tests
- (To be added)

### Integration Tests
- (To be added)

## Adding New Tests

1. Create your test file in the appropriate directory
2. Follow the naming convention: `test_*.php`
3. Use the test template structure:
   ```php
   <?php
   // Test description
   // Assertions and test logic
   ?>
   ```

## Notes

- Tests may require an active session (use test user ID: 1290033)
- Some tests require valid configuration in `.env`
- API tests simulate actual HTTP requests

