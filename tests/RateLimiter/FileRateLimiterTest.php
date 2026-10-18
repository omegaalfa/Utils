<?php

declare(strict_types=1);

namespace Tests\RateLimiter;

use InvalidArgumentException;
use Omegaalfa\Utils\RateLimiter\FileRateLimiter;
use PHPUnit\Framework\TestCase;

final class FileRateLimiterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/omega-rl-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testAllowsUpToTheLimitThenRejects(): void
    {
        $limiter = new FileRateLimiter($this->directory, maxAttempts: 3, decaySeconds: 60);

        self::assertTrue($limiter->attempt('api:1'));
        self::assertTrue($limiter->attempt('api:1'));
        self::assertTrue($limiter->attempt('api:1'));
        self::assertFalse($limiter->attempt('api:1'));
        self::assertFalse($limiter->attempt('api:1'));

        self::assertSame(0, $limiter->remaining('api:1'));
    }

    public function testKeysAreIsolated(): void
    {
        $limiter = new FileRateLimiter($this->directory, maxAttempts: 1, decaySeconds: 60);

        self::assertTrue($limiter->attempt('first'));
        self::assertFalse($limiter->attempt('first'));
        self::assertTrue($limiter->attempt('second'));
    }

    public function testCallbackRunsOnlyWhenAllowed(): void
    {
        $limiter = new FileRateLimiter($this->directory, maxAttempts: 1, decaySeconds: 60);
        $calls = 0;

        self::assertTrue($limiter->attempt('api:1', function () use (&$calls): void {
            $calls++;
        }));
        self::assertFalse($limiter->attempt('api:1', function () use (&$calls): void {
            $calls++;
        }));

        self::assertSame(1, $calls);
    }

    public function testResetClearsTheWindow(): void
    {
        $limiter = new FileRateLimiter($this->directory, maxAttempts: 1, decaySeconds: 60);

        self::assertTrue($limiter->attempt('api:1'));
        self::assertFalse($limiter->attempt('api:1'));

        $limiter->reset('api:1');

        self::assertTrue($limiter->attempt('api:1'));
        self::assertSame(0, $limiter->remaining('api:1'));
    }

    public function testRemainingReflectsAvailableBudget(): void
    {
        $limiter = new FileRateLimiter($this->directory, maxAttempts: 5, decaySeconds: 60);

        self::assertSame(5, $limiter->remaining('api:1'));
        self::assertTrue($limiter->attempt('api:1'));
        self::assertSame(4, $limiter->remaining('api:1'));
    }

    public function testTooManyAttemptsUsesCustomLimitsReadOnly(): void
    {
        $limiter = new FileRateLimiter($this->directory, maxAttempts: 10, decaySeconds: 60);

        self::assertFalse($limiter->tooManyAttempts('api:1', 2, 60));
        self::assertTrue($limiter->attempt('api:1'));
        self::assertTrue($limiter->attempt('api:1'));
        self::assertTrue($limiter->tooManyAttempts('api:1', 2, 60));

        // The read-only check must not have consumed budget from the instance window.
        self::assertSame(8, $limiter->remaining('api:1'));
    }

    public function testExpiredAttemptsArePrunedFromTheWindow(): void
    {
        $limiter = new FileRateLimiter($this->directory, maxAttempts: 1, decaySeconds: 1);

        self::assertTrue($limiter->attempt('api:1'));
        self::assertFalse($limiter->attempt('api:1'));

        $path = $this->directory . '/' . hash('sha256', 'api:1') . '.json';
        file_put_contents($path, json_encode([microtime(true) - 30], JSON_THROW_ON_ERROR));

        self::assertTrue($limiter->attempt('api:1'));
    }

    public function testRejectsInvalidConfiguration(): void
    {
        foreach ([0, -1] as $maxAttempts) {
            try {
                new FileRateLimiter($this->directory, maxAttempts: $maxAttempts);
                self::fail('Invalid max attempts must be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        try {
            new FileRateLimiter($this->directory, decaySeconds: 0);
            self::fail('Invalid decay must be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        foreach (['', "bad\0dir"] as $directory) {
            try {
                new FileRateLimiter($directory);
                self::fail('Invalid directories must be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRejectsInvalidKeys(): void
    {
        $limiter = new FileRateLimiter($this->directory);

        foreach (['', "bad\0key"] as $key) {
            try {
                $limiter->attempt($key);
                self::fail('Invalid keys must be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTooManyAttemptsRejectsInvalidLimits(): void
    {
        $limiter = new FileRateLimiter($this->directory);

        try {
            $limiter->tooManyAttempts('api:1', 0, 60);
            self::fail('Invalid max attempts must be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        $limiter->tooManyAttempts('api:1', 1, 0);
    }
}
