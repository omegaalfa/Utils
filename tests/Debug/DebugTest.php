<?php

declare(strict_types=1);

namespace Tests\Debug;

require_once dirname(__DIR__, 2) . '/helpers/debug.php';

use Omegaalfa\Utils\Debug\Debug;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionMethod;

final class DebugTest extends TestCase
{
    public function testDumpPrintsEveryValueWithoutTerminating(): void
    {
        ob_start();
        Debug::dump('omega', 42);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('Debug #1', $output);
        self::assertStringContainsString('omega', $output);
        self::assertStringContainsString('42', $output);
    }

    public function testShortAliasDelegatesToDump(): void
    {
        ob_start();
        ss(['active' => true]);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('active', $output);
        self::assertStringContainsString('true', $output);
    }

    public function testColorsCanBeForcedForCliOutput(): void
    {
        putenv('OMEGA_DEBUG_COLORS=1');
        putenv('NO_COLOR');

        try {
            ob_start();
            Debug::dump('colored');
            $output = ob_get_clean();
        } finally {
            putenv('OMEGA_DEBUG_COLORS');
        }

        self::assertIsString($output);
        self::assertStringContainsString("\033[1;36m", $output);
        self::assertStringContainsString("\033[0m", $output);
    }

    public function testNoColorStandardOverridesForcedColors(): void
    {
        putenv('OMEGA_DEBUG_COLORS=1');
        putenv('NO_COLOR=1');

        try {
            ob_start();
            Debug::dump('plain');
            $output = ob_get_clean();
        } finally {
            putenv('OMEGA_DEBUG_COLORS');
            putenv('NO_COLOR');
        }

        self::assertIsString($output);
        self::assertStringNotContainsString("\033[", $output);
    }

    public function testDumpWithNoValuesProducesNoOutput(): void
    {
        self::expectOutputString('');
        Debug::dump();
    }

    public function testOptionalGlobalHelpersAndNeverContractAreAvailable(): void
    {
        self::assertTrue(function_exists('dump_debug'));
        self::assertTrue(function_exists('ss'));
        self::assertTrue(function_exists('dd'));
        self::assertSame('never', (string) (new ReflectionFunction('dd'))->getReturnType());
        self::assertSame(
            'never',
            (string) (new ReflectionMethod(Debug::class, 'dd'))->getReturnType(),
        );
    }
}
