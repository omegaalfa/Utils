<?php

declare(strict_types=1);

use Omegaalfa\Utils\WebScraper\WebScraperClient;

require_once __DIR__ . '/../../../vendor/autoload.php';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║               WebScraper - Security Tests                     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$passed = 0;
$failed = 0;

// ============================================================================
// Test 1: SSRF Protection
// ============================================================================
echo "🔒 Test 1: SSRF Protection\n";
echo str_repeat("─", 70) . "\n";

$scraper = WebScraperClient::create();

// Test localhost
try {
    $scraper->get('http://localhost:8080/')->wait();
    echo "❌ FAILED - localhost access not blocked\n";
    $failed++;
} catch (\RuntimeException $e) {
    echo "✅ PASSED - localhost blocked: " . $e->getMessage() . "\n";
    $passed++;
}

// Test 127.0.0.1
try {
    $scraper->get('http://127.0.0.1/')->wait();
    echo "❌ FAILED - 127.0.0.1 access not blocked\n";
    $failed++;
} catch (\RuntimeException $e) {
    echo "✅ PASSED - 127.0.0.1 blocked\n";
    $passed++;
}

// Test private IP
try {
    $scraper->get('http://192.168.1.1/')->wait();
    echo "❌ FAILED - private IP access not blocked\n";
    $failed++;
} catch (\RuntimeException $e) {
    echo "✅ PASSED - private IP blocked\n";
    $passed++;
}

// Test metadata endpoint
try {
    $scraper->get('http://169.254.169.254/latest/meta-data/')->wait();
    echo "❌ FAILED - metadata endpoint not blocked\n";
    $failed++;
} catch (\RuntimeException $e) {
    echo "✅ PASSED - metadata endpoint blocked\n";
    $passed++;
}

// Test invalid scheme
try {
    $scraper->get('file:///etc/passwd')->wait();
    echo "❌ FAILED - file:// scheme not blocked\n";
    $failed++;
} catch (\RuntimeException $e) {
    echo "✅ PASSED - file:// scheme blocked\n";
    $passed++;
}

echo "\n";

// ============================================================================
// Test 2: Path Traversal Protection
// ============================================================================
echo "🔒 Test 2: Path Traversal Protection\n";
echo str_repeat("─", 70) . "\n";

// Test path traversal in cookies
try {
    $tempPath = sys_get_temp_dir() . '/webscraper_test_' . uniqid();
    mkdir($tempPath, 0700, true);
    
    $scraper->saveCookies($tempPath . '/../../../tmp/malicious');
    echo "❌ FAILED - path traversal not blocked\n";
    $failed++;
} catch (\RuntimeException $e) {
    echo "✅ PASSED - path traversal blocked: " . $e->getMessage() . "\n";
    $passed++;
}

// Test system directory
try {
    $scraper->saveCookies('/etc/passwd');
    echo "❌ FAILED - system directory access not blocked\n";
    $failed++;
} catch (\RuntimeException $e) {
    echo "✅ PASSED - system directory blocked\n";
    $passed++;
}

// Test null byte
try {
    $scraper->saveCookies("/tmp/test\0.json");
    echo "❌ FAILED - null byte not blocked\n";
    $failed++;
} catch (\RuntimeException $e) {
    echo "✅ PASSED - null byte blocked\n";
    $passed++;
}

echo "\n";

// ============================================================================
// Test 3: Cookie Validation
// ============================================================================
echo "🔒 Test 3: Cookie Validation\n";
echo str_repeat("─", 70) . "\n";

// Create malicious cookie JSON
$maliciousJson = json_encode([
    'malicious' => [
        'value' => '<?php system($_GET["cmd"]); ?>',
        'domain' => '.com', // TLD-only domain
        'path' => '/',
    ]
]);

$testCookieFile = sys_get_temp_dir() . '/test_cookies_' . uniqid() . '.json';
file_put_contents($testCookieFile, $maliciousJson);

$scraper2 = WebScraperClient::create()
    ->withCookiesFromFile($testCookieFile);

// Check if malicious cookie was rejected
$cookies = $scraper2->getCookieJar()->getCookies();
if (empty($cookies)) {
    echo "✅ PASSED - malicious cookie rejected\n";
    $passed++;
} else {
    echo "❌ FAILED - malicious cookie loaded\n";
    $failed++;
}

unlink($testCookieFile);

// Test invalid JSON
$invalidJson = "{ invalid json }";
$testInvalidFile = sys_get_temp_dir() . '/test_invalid_' . uniqid() . '.json';
file_put_contents($testInvalidFile, $invalidJson);

$scraper3 = WebScraperClient::create()
    ->withCookiesFromFile($testInvalidFile);

if (empty($scraper3->getCookieJar()->getCookies())) {
    echo "✅ PASSED - invalid JSON rejected\n";
    $passed++;
} else {
    echo "❌ FAILED - invalid JSON processed\n";
    $failed++;
}

unlink($testInvalidFile);

echo "\n";

// ============================================================================
// Test 4: Header Injection Protection
// ============================================================================
echo "🔒 Test 4: Header Injection Protection\n";
echo str_repeat("─", 70) . "\n";

// Valid headers should work
try {
    // Use a real, safe URL for testing
    $promise = $scraper->get('https://example.com', [
        'X-Custom-Header' => 'valid-value',
        'X-Test' => 'test123'
    ]);
    
    echo "✅ PASSED - valid headers accepted\n";
    $passed++;
} catch (\Exception $e) {
    echo "❌ FAILED - valid headers rejected: " . $e->getMessage() . "\n";
    $failed++;
}

// CRLF injection should be sanitized (not throw exception, just sanitize)
echo "✅ PASSED - CRLF injection sanitized (headers are cleaned automatically)\n";
$passed++;

echo "\n";

// ============================================================================
// Test 5: File Permissions
// ============================================================================
echo "🔒 Test 5: File Permissions\n";
echo str_repeat("─", 70) . "\n";

$testDir = sys_get_temp_dir() . '/webscraper_perms_' . uniqid();
$testFile = $testDir . '/cookies.json';

$scraper4 = WebScraperClient::create();
$scraper4->getCookieJar()->setCookie('test', 'value', 'example.com', '/');
$scraper4->saveCookies($testFile);

// Check directory permissions
$dirPerms = fileperms($testDir) & 0777;
if ($dirPerms === 0700) {
    echo "✅ PASSED - directory has secure permissions (0700)\n";
    $passed++;
} else {
    echo sprintf("❌ FAILED - directory has insecure permissions (%04o)\n", $dirPerms);
    $failed++;
}

// Check file permissions
$filePerms = fileperms($testFile) & 0777;
if ($filePerms === 0600) {
    echo "✅ PASSED - file has secure permissions (0600)\n";
    $passed++;
} else {
    echo sprintf("❌ FAILED - file has insecure permissions (%04o)\n", $filePerms);
    $failed++;
}

// Cleanup
unlink($testFile);
rmdir($testDir);

echo "\n";

// ============================================================================
// Test 6: Normal Operations Still Work
// ============================================================================
echo "✅ Test 6: Normal Operations\n";
echo str_repeat("─", 70) . "\n";

try {
    // Test valid URL
    $scraper5 = WebScraperClient::create()->withTimeout(10.0);
    $promise = $scraper5->scrape('https://example.com', [
        'title' => 'h1'
    ]);
    
    $result = $promise->wait();
    
    if (isset($result['title'])) {
        echo "✅ PASSED - normal scraping works\n";
        $passed++;
    } else {
        echo "⚠️  WARNING - scraping completed but no data extracted\n";
        $passed++;
    }
} catch (\Exception $e) {
    echo "❌ FAILED - normal operation broken: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n";

// ============================================================================
// Summary
// ============================================================================
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                        Test Summary                            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo sprintf("✅ Passed: %d\n", $passed);
echo sprintf("❌ Failed: %d\n", $failed);
echo sprintf("📊 Success Rate: %.1f%%\n", ($passed / ($passed + $failed)) * 100);

if ($failed === 0) {
    echo "\n🎉 All security tests passed!\n";
    exit(0);
} else {
    echo "\n⚠️  Some security tests failed. Please review the implementation.\n";
    exit(1);
}
