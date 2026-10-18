<?php

declare(strict_types=1);

namespace Tests\Cli;

use InvalidArgumentException;
use Omegaalfa\Utils\Cli\Application;
use Omegaalfa\Utils\Cli\Input;
use Omegaalfa\Utils\Cli\Output;
use Omegaalfa\Utils\Cli\ProgressBar;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CliTest extends TestCase
{
    public function testParsesArgumentsLongAndShortOptions(): void
    {
        $input = new Input([
            'user@example.com',
            '--role=admin',
            '--force',
            '-vq',
            '-n=42',
            '--',
            '--literal',
        ]);

        self::assertSame(['user@example.com', '--literal'], $input->arguments());
        self::assertSame('user@example.com', $input->argument(0));
        self::assertSame('fallback', $input->argument(99, 'fallback'));
        self::assertSame('admin', $input->option('role'));
        self::assertTrue($input->option('force'));
        self::assertTrue($input->option('v'));
        self::assertTrue($input->option('q'));
        self::assertSame('42', $input->option('n'));
        self::assertTrue($input->hasOption('role'));
    }

    public function testRunsRegisteredCommand(): void
    {
        [$output, $stdout] = $this->cliOutput();
        $application = new Application('Omega Console', '2.0.0');
        $application->command(
            'user:create',
            'Create a user',
            static function (Input $input, Output $output): int {
                $output->success('Created ' . $input->argument(0));
                return 0;
            },
        );

        $exitCode = $application->run(
            ['console', 'user:create', 'wesley@example.com'],
            $output,
        );

        self::assertSame(0, $exitCode);
        rewind($stdout);
        self::assertSame("Created wesley@example.com\n", stream_get_contents($stdout));
    }

    public function testRendersCommandListAndHelp(): void
    {
        [$output, $stdout] = $this->cliOutput();
        $application = new Application('Omega Console', '1.2.3');
        $application->command('cache:clear', 'Clear cache', static fn (): int => 0);

        self::assertSame(0, $application->run(['console', 'list'], $output));
        rewind($stdout);
        $contents = stream_get_contents($stdout);
        self::assertIsString($contents);
        self::assertStringContainsString('Omega Console 1.2.3', $contents);
        self::assertStringContainsString('cache:clear', $contents);
        self::assertStringContainsString('Clear cache', $contents);
    }

    public function testUnknownCommandReturnsFailure(): void
    {
        [$output, , $stderr] = $this->cliOutput();
        $application = new Application();

        self::assertSame(1, $application->run(['console', 'missing'], $output));
        rewind($stderr);
        self::assertStringContainsString('Command not found: missing', stream_get_contents($stderr));
    }

    public function testCommandExceptionIsRenderedAsFailure(): void
    {
        [$output, , $stderr] = $this->cliOutput();
        $application = new Application();
        $application->command('job:run', 'Run job', static function (): never {
            throw new RuntimeException('Job failed');
        });

        self::assertSame(1, $application->run(['console', 'job:run'], $output));
        rewind($stderr);
        self::assertStringContainsString('Job failed', stream_get_contents($stderr));
    }

    public function testOutputSupportsColorsQuestionsAndTables(): void
    {
        $stdout = fopen('php://memory', 'r+b');
        $stderr = fopen('php://memory', 'r+b');
        $stdin = fopen('php://memory', 'r+b');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        self::assertIsResource($stdin);
        fwrite($stdin, "\nWesley\n");
        rewind($stdin);

        $output = new Output($stdout, $stderr, $stdin, colors: true);
        self::assertSame('default', $output->ask('Name', 'default'));
        self::assertSame('Wesley', $output->ask('Name'));
        self::assertSame("\033[32mok\033[0m", $output->style('ok', 'green'));

        $output->table(
            ['Name', 'Age'],
            [['Wesley', 45], ['Ada', 36]],
        );

        rewind($stdout);
        $contents = stream_get_contents($stdout);
        self::assertIsString($contents);
        self::assertStringContainsString('| Wesley | 45  |', $contents);
        self::assertStringContainsString('+--------+-----+', $contents);
    }

    public function testRejectsInvalidCommandsColorsAndTableRows(): void
    {
        $application = new Application();

        try {
            $application->command('Invalid Name', '', static fn (): int => 0);
            self::fail('Invalid command name should fail.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        [$output] = $this->cliOutput();

        try {
            $output->style('text', 'orange');
            self::fail('Invalid color should fail.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        $output->table(['A', 'B'], [['only one']]);
    }

    public function testProgressBarRendersProgressAndFinishesOnItsOwnLine(): void
    {
        [$output, $stdout] = $this->cliOutput();
        $bar = new ProgressBar($output, max: 4, width: 8);

        $bar->start();
        $bar->advance();
        $bar->advance(2);
        $bar->finish();

        self::assertSame(4, $bar->current());
        self::assertSame(
            "\r[        ]   0% (0/4)" .
            "\r[==      ]  25% (1/4)" .
            "\r[======  ]  75% (3/4)" .
            "[========] 100% (4/4)" . PHP_EOL,
            $this->contents($stdout),
        );
    }

    public function testProgressBarClampsStepsAndSupportsResets(): void
    {
        [$output, $stdout] = $this->cliOutput();
        $bar = new ProgressBar($output, max: 2, width: 4);

        $bar->start();
        $bar->advance(50);
        self::assertSame(2, $bar->current());

        $bar->start();
        self::assertSame(0, $bar->current());
        self::assertStringContainsString('(0/2)', $this->contents($stdout));
    }

    public function testProgressBarRejectsInvalidConfigurationAndSteps(): void
    {
        [$output] = $this->cliOutput();

        foreach (
            [
                static fn () => new ProgressBar($output, max: 0),
                static fn () => new ProgressBar($output, max: 1, width: 0),
            ] as $operation
        ) {
            try {
                $operation();
                self::fail('Invalid progress bar configuration must be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        (new ProgressBar($output, max: 2))->advance(-1);
    }

    /** @param resource $stream */
    private function contents($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        self::assertIsString($contents);

        return $contents;
    }

    /** @return array{Output, resource, resource} */
    private function cliOutput(): array
    {
        $stdout = fopen('php://memory', 'r+b');
        $stderr = fopen('php://memory', 'r+b');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        return [new Output($stdout, $stderr, colors: false), $stdout, $stderr];
    }
}
