<?php

namespace App;

use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ProcessBuilder
{
    private array $command = [];

    private string $appContainerUser = 'laravel';

    private array $appContainerEnvironment = [];

    private bool $interactive = false;

    private bool $silent = false;

    private ?float $timeout = null;

    private bool $debug = false;

    public function __construct(private OutputInterface $output)
    {
        if (array_key_exists('DL_DEBUG', $_ENV)) {
            $this->debug = (bool) $_ENV['DL_DEBUG'] ?? false;
        }
    }

    public function make(): ProcessBuilder
    {
        return $this;
    }

    public function command(array|string $command): ProcessBuilder
    {
        $this->command = $this->asArray($command);

        return $this;
    }

    public function run(array|string $command = null): Process
    {
        if (! $command || empty($command)) {
            $command = $this->command;
        }
        if (empty($command)) {
            throw new RuntimeException('No Process defined');
        }
        $process = new Process($this->asArray($command));
        if ($this->debug) {
            fwrite(STDERR, sprintf('Running command (%s): %s'.PHP_EOL, $this->interactive ? 'tty' : 'pty', $process->getCommandLine()));
        }
        if ($this->interactive) {
            $process->setTty(true);
        } else {
            $process->setPty(true);
        }
        $process->setTimeout($this->timeout);
        $callback = function ($type, $buffer) {
            if (! $this->silent && ! $this->debug) {
                $this->output->write($buffer);
            }
        };
        $process->run($callback);
        if ($this->debug) {
            fwrite(STDERR, sprintf('Exit code: %d | Output length: %d'.PHP_EOL, $process->getExitCode(), strlen($process->getOutput())));
        }

        return $process;
    }

    public function getExitCode(): int
    {
        return $this->run()->getExitCode();
    }

    public function getOutput(): string
    {
        return $this->run()->getOutput();
    }

    public function artisan(array|string $command): ProcessBuilder
    {
        // Ensure TTY mode for interactive commands
        $command = $this->asArray($command);
        $interactive = in_array($command[0], [
            'tinker',
            'docs',
        ], true);

        return $this->interactive($interactive)->php([
            'artisan',
            '--ansi',
            ...$command,
        ]);
    }

    public function php(array|string $command): ProcessBuilder
    {
        return $this->app([
            'php',
            ...$this->asArray($command),
        ]);
    }

    public function app(array|string $command): ProcessBuilder
    {
        $command = $this->asArray($command);
        $execArgs = [
            '-u',
            $this->appContainerUser,
            ...collect($this->appContainerEnvironment)->flatMap(fn ($value, $name) => [
                '-e',
                "$name=$value",
            ])->all(),
        ];
        if (! $this->interactive) {
            array_unshift($execArgs, '-T');
        }

        return $this->dockerCompose([
            'exec',
            ...$execArgs,
            'app',
            ...$this->asArray($command),
        ]);
    }

    public function dockerCompose(array|string $command): ProcessBuilder
    {
        return $this->command([
            'docker-compose',
            ...$this->asArray($command),
        ]);
    }

    public function composer(array|string $command): ProcessBuilder
    {
        return $this->app([
            'composer',
            '--ansi',
            ...$this->asArray($command),
        ]);
    }

    public function user(string $username): ProcessBuilder
    {
        $this->appContainerUser = $username;

        return $this;
    }

    public function setEnvironment(array $environment): ProcessBuilder
    {
        $this->appContainerEnvironment = $environment;

        return $this;
    }

    public function mergeEnvironment(array $environment): ProcessBuilder
    {
        $this->appContainerEnvironment = array_merge($this->appContainerEnvironment, $environment);

        return $this;
    }

    public function xdebug(): ProcessBuilder
    {
        return $this->mergeEnvironment(['XDEBUG_SESSION' => 1]);
    }

    public function interactive(bool $interactive = true): ProcessBuilder
    {
        $this->interactive = $interactive;

        return $this;
    }

    public function silent(bool $silent = true): ProcessBuilder
    {
        $this->silent = $silent;

        return $this;
    }

    public function timeout(?float $timeout): ProcessBuilder
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function debug(bool $debug = true): ProcessBuilder
    {
        $this->debug = $debug;

        return $this;
    }

    private function asArray(array|string $command): array
    {
        if (is_string($command)) {
            return collect(explode(' ', $command))->map(fn ($arg) => trim($arg))->filter()->all();
        }

        return $command;
    }
}
