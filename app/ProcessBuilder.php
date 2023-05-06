<?php

namespace App;

use Illuminate\Support\Str;
use function in_array;
use function is_string;
use RuntimeException;
use function strlen;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ProcessBuilder
{
    /** @var array<int, string> */
    private array $command = [];

    private string $appContainerUser = 'laravel';

    /** @var array<string, string|int> */
    private array $appContainerEnvironment = [];

    private bool $interactive;

    private bool $silent = false;

    private ?float $timeout = null;

    private bool $debug;

    private LocalEnvironment $environment;

    public function __construct(private OutputInterface $output)
    {
        $this->environment = app(LocalEnvironment::class);
        $this->interactive = $this->environment->shouldForceTty();
        $this->debug = $this->environment->debug();
    }

    public function make(): self
    {
        return $this;
    }

    /**
     * @param  array|string[]|string  $command
     * @return $this
     */
    public function command(array|string $command): self
    {
        $this->command = $this->asArray($command);

        return $this;
    }

    /**
     * @param  array|string[]|string|null  $command
     */
    public function run(array|string $command = null): Process
    {
        if (empty($command)) {
            $command = $this->command;
        }
        if (empty($command)) {
            throw new RuntimeException('No Process defined');
        }
        $process = new Process($this->asArray($command), getcwd() ?: '.', $_ENV);
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
            if (! $this->silent) {
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
        return $this->run()->getExitCode() ?? 1;
    }

    public function getOutput(): string
    {
        return $this->run()->getOutput();
    }

    /**
     * @param  array|string[]|string  $command
     */
    public function artisan(array|string $command): self
    {
        $command = $this->asArray($command);
        if (! in_array($command[0], ['test', 'dusk'], true) && ! in_array('--no-ansi', $command, true)) {
            $command[] = '--ansi';
        }
        // Ensure TTY mode for interactive commands
        if (! in_array($command[0], [
            'test',
            'list',
        ])) {
            $this->interactive();
        }

        return $this->php([
            'artisan',
            ...$command,
        ]);
    }

    /**
     * @param  array|string[]|string  $command
     */
    public function php(array|string $command): self
    {
        return $this->app([
            'php',
            ...$this->asArray($command),
        ]);
    }

    /**
     * @param  array|string[]|string  $command
     */
    public function app(array|string $command): self
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
            $execArgs = ['-T', ...$execArgs];
            //$execArgs[] = '-T';
        }

        return $this->dockerCompose([
            'exec',
            ...$execArgs,
            'app',
            ...$this->asArray($command),
        ]);
    }

    /**
     * @param  array|string[]|string  $command
     */
    public function dockerCompose(array|string $command): self
    {
        return $this->command([
            'docker',
            'compose',
            ...$this->asArray($command),
        ]);
    }

    /**
     * @param  array|string[]|string  $command
     */
    public function composer(array|string $command): self
    {
        return $this->app([
            'composer',
            ...$this->asArray($command),
            '--ansi',
        ]);
    }

    public function query(string $query): self
    {
        if (($username = $this->environment->getEnvironment('DB_USERNAME'))) {
            /** @var string $username */
            $command = ['mysql', "-u$username"];
            if ($password = $this->environment->getEnvironment('DB_PASSWORD')) {
                /** @var string $password */
                $command[] = "-p$password";
            }
            $query = Str::finish($query, ';');

            return $this->dockerCompose([
                'exec',
                'db',
                ...$command,
                '-e',
                $query,
            ]);
        }

        return $this;
    }

    public function user(string $username): self
    {
        $this->appContainerUser = $username;

        return $this;
    }

    /**
     * @param  array<string, string|int>  $environment
     */
    public function setEnvironment(array $environment): self
    {
        $this->appContainerEnvironment = $environment;

        return $this;
    }

    /**
     * @param  array<string, string|int>  $environment
     */
    public function mergeEnvironment(array $environment): self
    {
        $this->appContainerEnvironment = array_merge($this->appContainerEnvironment, $environment);

        return $this;
    }

    public function xdebug(): self
    {
        return $this->mergeEnvironment(['XDEBUG_SESSION' => 1, 'XDEBUG_CONFIG' => 'idekey=PHPSTORM']);
    }

    public function interactive(bool $interactive = true): self
    {
        $this->interactive = $interactive && ! $this->silent;

        return $this;
    }

    public function silent(bool $silent = true): self
    {
        $this->silent = $silent;
        $this->interactive = false;

        return $this;
    }

    public function timeout(?float $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function debug(bool $debug = true): self
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * @param  array|string[]|string  $command
     * @return array|string[]
     */
    private function asArray(array|string $command): array
    {
        if (is_string($command)) {
            return collect(explode(' ', $command))->map(fn ($arg) => trim($arg))->filter()->all();
        }

        return $command;
    }
}
