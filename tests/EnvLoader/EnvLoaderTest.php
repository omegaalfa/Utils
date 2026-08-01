<?php

declare(strict_types=1);

namespace Tests\EnvLoader;

use InvalidArgumentException;
use Omegaalfa\Utils\EnvLoader\EnvLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvLoaderTest extends TestCase
{
    private string $directory;

    /** @var list<string> */
    private array $keys = [];

    private int $fileCount = 0;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/omegaalfa-utils-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
    }

    protected function tearDown(): void
    {
        foreach ($this->keys as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
        foreach (new \FilesystemIterator($this->directory) as $file) {
            assert($file instanceof \SplFileInfo);
            unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testLoadsSupportedSyntaxFromDirectoryPath(): void
    {
        $this->envFile(<<<'ENV'
            # application settings
            APP_NAME="Omega Utils"
            export APP_MODE=production # inline comment
            EMPTY=
            SINGLE='literal value'
            ESCAPED="a \"quote\" and \\ slash"
            ENV);

        EnvLoader::load($this->directory, required: true);

        self::assertSame('Omega Utils', EnvLoader::get('APP_NAME'));
        self::assertSame('production', EnvLoader::get('APP_MODE'));
        self::assertSame('', EnvLoader::get('EMPTY'));
        self::assertSame('literal value', EnvLoader::get('SINGLE'));
        self::assertSame('a "quote" and \ slash', EnvLoader::get('ESCAPED'));
    }

    public function testUsesCurrentWorkingDirectoryByDefault(): void
    {
        $this->envFile('DEFAULT_PATH_VALUE=loaded');
        $previousDirectory = getcwd();
        self::assertNotFalse($previousDirectory);

        try {
            self::assertTrue(chdir($this->directory));
            EnvLoader::load();
            self::assertSame('loaded', EnvLoader::get('DEFAULT_PATH_VALUE'));
        } finally {
            chdir($previousDirectory);
        }
    }

    public function testControlsWhetherExistingValuesAreOverwritten(): void
    {
        $this->setEnvironment('EXISTING_VALUE', 'process');
        EnvLoader::load($this->envFile('EXISTING_VALUE=file'));
        self::assertSame('process', EnvLoader::get('EXISTING_VALUE'));

        $this->setEnvironment('OVERWRITTEN_VALUE', 'old');
        EnvLoader::load($this->envFile('OVERWRITTEN_VALUE=new'), overwrite: true);
        self::assertSame('new', EnvLoader::get('OVERWRITTEN_VALUE'));
    }

    public function testMissingOptionalFileIsIgnored(): void
    {
        EnvLoader::load($this->directory . '/missing.env');
        self::assertFalse(EnvLoader::has('MISSING_OPTIONAL_VALUE'));
    }

    public function testMissingRequiredFileThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Environment file not found');
        EnvLoader::load($this->directory . '/missing.env', required: true);
    }

    #[DataProvider('invalidLines')]
    public function testRejectsMalformedEntries(string $line, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        EnvLoader::load($this->envFile($line));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidLines(): iterable
    {
        yield 'missing separator' => ['INVALID', 'Invalid .env entry'];
        yield 'invalid name' => ['1INVALID=value', 'Invalid environment variable name'];
        yield 'unclosed quote' => ['VALUE="unclosed', 'Unclosed quoted value'];
        yield 'content after quote' => ['VALUE="valid" invalid', 'Unexpected content'];
        yield 'NUL byte' => ["VALUE=bad\0value", 'NUL byte'];
    }

    public function testTypedAccessorsAndDefaults(): void
    {
        $this->setEnvironment('PORT_NUMBER', '-42');
        $this->setEnvironment('FEATURE_ON', 'YeS');
        $this->setEnvironment('FEATURE_OFF', 'off');

        self::assertSame(-42, EnvLoader::getInt('PORT_NUMBER'));
        self::assertTrue(EnvLoader::getBool('FEATURE_ON'));
        self::assertFalse(EnvLoader::getBool('FEATURE_OFF'));
        self::assertSame(8080, EnvLoader::getInt('ABSENT_INTEGER', 8080));
        self::assertTrue(EnvLoader::getBool('ABSENT_BOOLEAN', true));
        self::assertSame('fallback', EnvLoader::get('ABSENT_STRING', 'fallback'));
    }

    public function testIntegerAccessorRejectsInvalidValue(): void
    {
        $this->setEnvironment('INVALID_INTEGER', '1.5');
        $this->expectException(InvalidArgumentException::class);
        EnvLoader::getInt('INVALID_INTEGER');
    }

    public function testBooleanAccessorRejectsInvalidValue(): void
    {
        $this->setEnvironment('INVALID_BOOLEAN', 'maybe');
        $this->expectException(InvalidArgumentException::class);
        EnvLoader::getBool('INVALID_BOOLEAN');
    }

    public function testRequireReturnsValueAndRejectsEmptyValue(): void
    {
        $this->setEnvironment('REQUIRED_VALUE', 'available');
        self::assertSame('available', EnvLoader::require('REQUIRED_VALUE'));

        $this->setEnvironment('EMPTY_REQUIRED_VALUE', '');
        $this->expectException(RuntimeException::class);
        EnvLoader::require('EMPTY_REQUIRED_VALUE');
    }

    public function testRejectsInvalidKeyInPublicApi(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnvLoader::get('INVALID-KEY');
    }

    public function testReadsServerScalarsAndRejectsNonScalarValues(): void
    {
        $this->keys[] = 'SERVER_VALUE';
        $_SERVER['SERVER_VALUE'] = 1234;
        self::assertSame('1234', EnvLoader::get('SERVER_VALUE'));

        $this->keys[] = 'INVALID_VALUE';
        $_ENV['INVALID_VALUE'] = ['not', 'scalar'];
        $this->expectException(RuntimeException::class);
        EnvLoader::get('INVALID_VALUE');
    }

    public function testRejectsFilesLargerThanSafetyLimit(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds the 1 MiB safety limit');
        EnvLoader::load($this->envFile(str_repeat('A', 1_048_577)));
    }

    public function testStrictPermissionsRejectBroadUnixPermissions(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Unix file permissions are not available on Windows.');
        }
        $file = $this->envFile('PRIVATE_VALUE=secret');
        chmod($file, 0644);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('permissions are too broad');
        EnvLoader::load($file, strictPermissions: true);
    }

    private function envFile(string $contents): string
    {
        preg_match_all('/^[A-Za-z_][A-Za-z0-9_]*(?==)/m', $contents, $matches);
        foreach ($matches[0] as $key) {
            $this->keys[] = $key;
        }
        $this->fileCount++;
        $file = $this->directory . ($this->fileCount === 1 ? '/.env' : '/.env-' . $this->fileCount);
        self::assertNotFalse(file_put_contents($file, $contents));
        return $file;
    }

    private function setEnvironment(string $key, string $value): void
    {
        $this->keys[] = $key;
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}
