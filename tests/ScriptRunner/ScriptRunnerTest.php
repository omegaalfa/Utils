<?php

declare(strict_types=1);

namespace Tests\ScriptRunner;

use InvalidArgumentException;
use Omegaalfa\Utils\ScriptRunner\Script;
use Omegaalfa\Utils\ScriptRunner\ScriptExecutionResult;
use Omegaalfa\Utils\ScriptRunner\ScriptExecutor;
use Omegaalfa\Utils\ScriptRunner\ScriptFinder;
use Omegaalfa\Utils\ScriptRunner\ScriptRunner;
use Omegaalfa\Utils\ScriptRunner\Terminal;
use Omegaalfa\Utils\ScriptRunner\TerminalMenu;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ScriptRunnerTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $path = sys_get_temp_dir() . '/omega-script-runner-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($path, 0700, true));
        $this->temporaryDirectory = $path;
    }

    protected function tearDown(): void
    {
        $this->remove($this->temporaryDirectory);
    }

    public function testFinderListsOnlySafeEntriesInNaturalOrder(): void
    {
        mkdir($this->path('z-dir'));
        mkdir($this->path('a-dir'));
        file_put_contents($this->path('10.php'), '<?php');
        file_put_contents($this->path('2.php'), '<?php');
        file_put_contents($this->path('.hidden.php'), '<?php');
        file_put_contents($this->path('notes.txt'), 'ignored');
        symlink($this->path('2.php'), $this->path('linked.php'));
        symlink($this->path('a-dir'), $this->path('linked-dir'));

        $entries = (new ScriptFinder([$this->temporaryDirectory]))->entries($this->temporaryDirectory);

        self::assertSame(['a-dir', 'z-dir'], array_column($entries['directories'], 'name'));
        self::assertSame(['2.php', '10.php'], array_map(
            static fn (Script $script): string => $script->name(),
            $entries['scripts'],
        ));
    }

    public function testFinderRejectsMissingAndSymbolicRoots(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScriptFinder([$this->path('missing')]);
    }

    public function testFinderRejectsSymbolicRoot(): void
    {
        $real = $this->path('real');
        mkdir($real);
        $link = $this->path('link');
        symlink($real, $link);

        $this->expectException(InvalidArgumentException::class);
        new ScriptFinder([$link]);
    }

    public function testFinderRejectsDirectoryOutsideRootAndSimilarPrefix(): void
    {
        $root = $this->path('root');
        $similar = $this->path('root-evil');
        mkdir($root);
        mkdir($similar);
        $finder = new ScriptFinder([$root]);

        $this->expectException(RuntimeException::class);
        $finder->entries($similar);
    }

    public function testExecutorUsesArrayCommandWithoutShellInterpretationAndScriptDirectoryAsCwd(): void
    {
        $name = 'script with spaces ; echo INJECTED.php';
        $path = $this->path($name);
        file_put_contents($path, '<?php echo basename(getcwd()), "|SAFE";');
        $executor = new ScriptExecutor([$this->temporaryDirectory]);

        $result = $executor->execute(new Script($path, $name));

        self::assertInstanceOf(ScriptExecutionResult::class, $result);
        self::assertSame(basename($this->temporaryDirectory) . '|SAFE', $result->stdout);
        self::assertStringNotContainsString('INJECTED' . PHP_EOL, $result->stdout);
        self::assertSame('', $result->stderr);
        self::assertSame(0, $result->exitCode);

        $sourcePath = (new \ReflectionClass(ScriptExecutor::class))->getFileName();
        self::assertIsString($sourcePath);
        $source = file_get_contents($sourcePath);
        self::assertIsString($source);
        self::assertStringContainsString('[$this->phpBinary, $path]', $source);
    }

    public function testLargeStdoutAndStderrRemainSeparateWithoutDeadlock(): void
    {
        $path = $this->path('large.php');
        file_put_contents(
            $path,
            '<?php $out = str_repeat("O", 2_000_000); $err = str_repeat("E", 2_000_000); fwrite(STDOUT, $out); fwrite(STDERR, $err); exit(23);',
        );

        $result = (new ScriptExecutor([$this->temporaryDirectory]))
            ->execute(new Script($path, 'large.php'));

        self::assertSame(2_000_000, strlen($result->stdout));
        self::assertSame(2_000_000, strlen($result->stderr));
        self::assertSame('O', $result->stdout[0]);
        self::assertSame('E', $result->stderr[0]);
        self::assertSame(23, $result->exitCode);
    }

    public function testTemporaryOutputFilesAreRemoved(): void
    {
        $path = $this->path('clean.php');
        file_put_contents($path, '<?php echo "ok";');
        $before = $this->temporaryOutputs();

        (new ScriptExecutor([$this->temporaryDirectory]))->execute(new Script($path, 'clean.php'));

        self::assertSame($before, $this->temporaryOutputs());
    }

    public function testOutsidePathAndSimilarRootPrefixCannotExecute(): void
    {
        $root = $this->path('allowed');
        $similar = $this->path('allowed-copy');
        mkdir($root);
        mkdir($similar);
        $outside = $similar . '/outside.php';
        file_put_contents($outside, '<?php');

        $this->expectException(RuntimeException::class);
        (new ScriptExecutor([$root]))->execute(new Script($outside, 'outside.php'));
    }

    public function testRemovedAndReplacedBySymlinkScriptsAreRejected(): void
    {
        $path = $this->path('selected.php');
        $outside = $this->path('outside.txt');
        file_put_contents($path, '<?php');
        file_put_contents($outside, '<?php echo "outside";');
        $script = new Script($path, 'selected.php');
        unlink($path);
        symlink($outside, $path);

        $this->expectException(RuntimeException::class);
        (new ScriptExecutor([$this->temporaryDirectory]))->execute($script);
    }

    public function testRemovedScriptIsRejected(): void
    {
        $path = $this->path('removed.php');
        file_put_contents($path, '<?php');
        $script = new Script($path, 'removed.php');
        unlink($path);

        $this->expectException(RuntimeException::class);
        (new ScriptExecutor([$this->temporaryDirectory]))->execute($script);
    }

    public function testStartFailureDiffersFromNormalNonZeroExit(): void
    {
        $path = $this->path('failure.php');
        file_put_contents($path, '<?php fwrite(STDERR, "application failure"); exit(9);');
        $result = (new ScriptExecutor([$this->temporaryDirectory]))
            ->execute(new Script($path, 'failure.php'));
        self::assertSame(9, $result->exitCode);
        self::assertSame('application failure', $result->stderr);

        try {
            (new ScriptExecutor([$this->temporaryDirectory], $this->path('missing-php')))
                ->execute(new Script($path, 'failure.php'));
            self::fail('A missing executable must fail to start.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Unable to start PHP process', $exception->getMessage());
        }
    }

    public function testMenuRetriesInvalidChoiceAndHandlesZeroAndEof(): void
    {
        [$terminal, $output] = $this->terminal("invalid\n7\n1\n");
        self::assertSame(1, (new TerminalMenu($terminal))->choose('Menu', ['Script'], 'Exit'));
        self::assertStringContainsString('Opcao invalida', $this->contents($output));

        [$terminal] = $this->terminal('');
        self::assertSame(0, (new TerminalMenu($terminal))->choose('Menu', [], 'Exit'));
    }

    public function testRunnerNavigatesExecutesAndAllowsRerun(): void
    {
        file_put_contents($this->path('hello.php'), '<?php echo "hello runner";');
        [$terminal, $output, $error] = $this->terminal("1\n1\n1\n0\n");

        (new ScriptRunner([$this->temporaryDirectory], PHP_BINARY, $terminal))->run();

        $text = $this->contents($output);
        self::assertSame(2, substr_count($text, 'Executando:'));
        self::assertStringContainsString('hello runner', $text);
        self::assertStringContainsString('Exit Code: 0', $text);
        self::assertSame('', $this->contents($error));
    }

    public function testRunnerReportsEmptyRootAndConfigurationErrors(): void
    {
        [$terminal, $output] = $this->terminal('');
        (new ScriptRunner([$this->temporaryDirectory], PHP_BINARY, $terminal))->run();
        self::assertStringContainsString('Nenhum script PHP', $this->contents($output));

        [$terminal, , $error] = $this->terminal('');
        (new ScriptRunner([$this->path('missing')], PHP_BINARY, $terminal))->run();
        self::assertStringContainsString('Erro:', $this->contents($error));
    }

    /** @return array{Terminal, resource, resource} */
    private function terminal(string $input): array
    {
        $in = fopen('php://memory', 'r+');
        $out = fopen('php://memory', 'r+');
        $error = fopen('php://memory', 'r+');
        self::assertIsResource($in);
        self::assertIsResource($out);
        self::assertIsResource($error);
        fwrite($in, $input);
        rewind($in);

        return [new Terminal($in, $out, $error), $out, $error];
    }

    /** @param resource $stream */
    private function contents($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        self::assertIsString($contents);
        return $contents;
    }

    /** @return list<string> */
    private function temporaryOutputs(): array
    {
        $paths = array_merge(
            glob(sys_get_temp_dir() . '/omega-run-out-*') ?: [],
            glob(sys_get_temp_dir() . '/omega-run-err-*') ?: [],
        );
        sort($paths);
        return $paths;
    }

    private function path(string $name): string
    {
        return $this->temporaryDirectory . DIRECTORY_SEPARATOR . $name;
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $entries = scandir($path);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($path);
    }
}
