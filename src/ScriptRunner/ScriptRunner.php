<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\ScriptRunner;

use Throwable;

final readonly class ScriptRunner
{
    /** @param list<string> $directories */
    public function __construct(
        private array     $directories,
        private string    $phpBinary = PHP_BINARY,
        private ?Terminal $terminal = null,
    )
    {
    }

    /**
     * @return void
     */
    public function run(): void
    {
        $terminal = $this->terminal ?? new Terminal();
        $terminal->clear();
        $terminal->title('Omegaalfa Script Runner');

        try {
            $finder = new ScriptFinder($this->directories);
            if (!$finder->hasScripts()) {
                $terminal->writeln('Nenhum script PHP encontrado nos diretorios registrados.');
                return;
            }

            $menu = new TerminalMenu($terminal);
            $executor = new ScriptExecutor($finder->allowedDirectories(), $this->phpBinary);
            $roots = $finder->roots();

            while (true) {
                $choice = $menu->choose(
                    'Escolha um diretorio',
                    array_column($roots, 'name'),
                    'Sair',
                );
                if ($choice === 0) {
                    return;
                }

                $rootIndex = $choice - 1;
                if (!isset($roots[$rootIndex])) {
                    continue;
                }

                if ($this->browse($roots[$rootIndex]['path'], $finder, $executor, $menu, $terminal)) {
                    return;
                }
            }
        } catch (Throwable $exception) {
            $terminal->error("Erro: {$exception->getMessage()}");
        }
    }

    /**
     * @param string $root
     * @param ScriptFinder $finder
     * @param ScriptExecutor $executor
     * @param TerminalMenu $menu
     * @param Terminal $terminal
     * @return bool
     */
    private function browse(
        string         $root,
        ScriptFinder   $finder,
        ScriptExecutor $executor,
        TerminalMenu   $menu,
        Terminal       $terminal,
    ): bool
    {
        $stack = [$root];

        while (true) {
            $current = $stack[array_key_last($stack)];
            $entries = $finder->entries($current);
            $labels = [];

            foreach ($entries['directories'] as $directory) {
                $labels[] = $directory['name'] . '/';
            }
            foreach ($entries['scripts'] as $script) {
                $labels[] = $script->name();
            }

            if ($labels === []) {
                $terminal->writeln('Nenhum script PHP encontrado neste diretorio.');
                $terminal->writeln();
            }

            $relative = ltrim(substr($current, strlen($root)), DIRECTORY_SEPARATOR);
            $title = basename($root) . ($relative === ''
                    ? '/'
                    : '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative) . '/');
            $choice = $menu->choose($title, $labels, 'Voltar');

            if ($choice === 0) {
                if (count($stack) === 1) {
                    return false;
                }
                array_pop($stack);
                continue;
            }

            $directoryCount = count($entries['directories']);
            if ($choice <= $directoryCount) {
                $directoryIndex = $choice - 1;
                if (!isset($entries['directories'][$directoryIndex])) {
                    continue;
                }
                $stack[] = $entries['directories'][$directoryIndex]['path'];
                continue;
            }

            $scriptIndex = $choice - $directoryCount - 1;
            if (!isset($entries['scripts'][$scriptIndex])) {
                continue;
            }
            if ($this->execute($entries['scripts'][$scriptIndex], $executor, $menu, $terminal)) {
                return true;
            }
        }
    }

    /**
     * @param Script $script
     * @param ScriptExecutor $executor
     * @param TerminalMenu $menu
     * @param Terminal $terminal
     * @return bool
     */
    private function execute(
        Script         $script,
        ScriptExecutor $executor,
        TerminalMenu   $menu,
        Terminal       $terminal,
    ): bool
    {
        while (true) {
            $terminal->writeln();
            $terminal->writeln('Executando:');
            $terminal->writeln();
            $terminal->writeln($script->relativePath);
            $terminal->writeln();

            try {
                $result = $executor->execute($script);
                if ($result->stdout !== '') {
                    $terminal->write($result->stdout);
                    if (!str_ends_with($result->stdout, "\n")) {
                        $terminal->writeln();
                    }
                }
                if ($result->stderr !== '') {
                    $terminal->error(rtrim($result->stderr, "\r\n"));
                }

                $terminal->writeln();
                $terminal->writeln(str_repeat('=', 32));
                $terminal->writeln('Processo finalizado');
                $terminal->writeln();
                $terminal->writeln("Exit Code: {$result->exitCode}");
                $terminal->writeln(str_repeat('=', 32));
                $terminal->writeln();
            } catch (Throwable $exception) {
                $terminal->error("Falha ao executar PHP: {$exception->getMessage()}");
                return false;
            }

            $choice = $menu->choose(
                'Proxima acao',
                ['Executar novamente', 'Escolher outro script'],
                'Sair',
            );
            if ($choice === 0) {
                return true;
            }
            if ($choice === 2) {
                return false;
            }
        }
    }
}
