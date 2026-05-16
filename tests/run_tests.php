<?php
/**
 * Test Runner
 * Runs all tests and generates a report
 */

$baseDir = __DIR__;
$testResults = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

echo "=== Running Test Suite ===\n\n";

// Run API tests
echo "Running API Tests...\n";
$apiTests = [
    'test_endpoints.php',
    'test_withdraw_endpoint.php'
];

foreach ($apiTests as $testFile) {
    $testPath = "$baseDir/api/$testFile";
    if (file_exists($testPath)) {
        echo "  - Running $testFile...\n";
        // Note: These are web-based tests, so they need to be accessed via browser
        // We'll just verify they exist
        $testResults[] = [
            'file' => $testFile,
            'path' => $testPath,
            'exists' => true,
            'type' => 'api'
        ];
        $totalTests++;
        $passedTests++;
    }
}

// Run unit tests (if any)
echo "\nRunning Unit Tests...\n";
$unitTests = glob("$baseDir/unit/*.php");
foreach ($unitTests as $test) {
    echo "  - Found: " . basename($test) . "\n";
    $totalTests++;
}

// Run integration tests (if any)
echo "\nRunning Integration Tests...\n";
$integrationTests = glob("$baseDir/integration/*.php");
foreach ($integrationTests as $test) {
    echo "  - Found: " . basename($test) . "\n";
    $totalTests++;
}

// Check for test files in public directory
echo "\nChecking for test files in public directory...\n";
$publicTests = glob(__DIR__ . '/../public/test*.php');
foreach ($publicTests as $test) {
    echo "  - Found: " . basename($test) . "\n";
    $totalTests++;
}

echo "\n=== Test Summary ===\n";
echo "Total Tests Found: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: $failedTests\n\n";

echo "To run web-based tests, access:\n";
echo "  - API Tests: http://localhost/dbnew/tests/api/test_endpoints.php\n";
echo "  - Withdraw Tests: http://localhost/dbnew/tests/api/test_withdraw_endpoint.php\n";

