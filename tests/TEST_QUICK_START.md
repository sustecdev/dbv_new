# Test Suite Quick Start Guide

## Quick Test Access

### 1. API Endpoint Tests
Open in browser:
```
http://localhost/dbnew/tests/api/test_endpoints.php
```
Tests basic endpoint accessibility and response formats.

### 2. Withdrawal Endpoint Tests
Open in browser:
```
http://localhost/dbnew/tests/api/test_withdraw_endpoint.php
```
Tests withdrawal endpoint with different request methods.

### 3. Stellar Network Tests
Open in browser:
```
http://localhost/dbnew/tests/api/test_stellar_endpoints.php
```
Comprehensive tests for all Stellar network endpoints.

## Running Tests from Command Line

```bash
php tests/run_tests.php
```

## Test Results

All tests generate visual output with:
- ✓ Green = Pass
- ✗ Red = Fail
- Yellow = Info/Warnings

## Test User

Default test user ID: `1290033`

If you need to change this, edit the test files and update the `$_SESSION['uid']` value.

## Notes

- Tests require Apache/PHP to be running
- Some tests require database connection
- Full functionality tests require valid credentials
- API tests simulate real HTTP requests

## Troubleshooting

If tests fail:
1. Check Apache is running
2. Verify database connection in `app/Config/.env`
3. Ensure test user exists in database
4. Check Apache error logs: `C:\xampp\apache\logs\error.log`

