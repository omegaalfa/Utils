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
