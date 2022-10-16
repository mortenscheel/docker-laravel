<?php

namespace App\Service;

use function is_resource;
use function is_string;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Process\Process;

class ProcessService
{
    public bool $debug = false;

    private bool $interactive = false;

    private OutputInterface $output;

    public function __construct(
        private string $execAppUser = 'laravel',
        private array $execAppEnvironament = [])
    {
        $this->output = new StreamOutput(fopen('php://stdout', 'w'));
    }

    public function asUser(string $user): self
    {
        return new ProcessService($user, $this->execAppEnvironament);
    }

    public function withEnvironmentVariables(array $environment = []): self
    {
        return new ProcessService(
            $this->execAppUser,
            collect($environment)->flatMap(fn ($value, $name) => [
                '-e',
                "$name=$value",
            ])->merge($this->execAppEnvironament)->all()
        );
    }

    public function withXdebug(): self
    {
        return $this->withEnvironmentVariables([
            'XDEBUG_SESSION' => 1,
        ]);
    }

    public function interactive(bool $interactive = true): self
    {
        $this->interactive = $interactive;

        return $this;
    }

    public function create(string|array $command): Process
    {
        if (is_string($command)) {
            return Process::fromShellCommandline($command);
        }

        return new Process($command);
    }

    public function run(string|array $command, bool $silent = false): Process
    {
        $process = $this->create($command);
        if ($this->debug) {
            fwrite(STDERR, 'Running '.$process->getCommandLine().PHP_EOL);
        }
        $process->setTimeout(null);
        if ($this->interactive) {
            $process->setTty(true);
        } else {
            $process->setPty(true);
        }
        $callback = $silent ? null : fn ($type, $buffer) => $this->output->write($buffer);
        $process->run($callback);

        return $process;
    }

    public function composer(array $command): Process
    {
        return $this->app([
            'composer',
            ...$command,
        ]);
    }

    public function artisan(array $command): Process
    {
        return $this->php([
            'artisan',
            ...$command,
        ]);
    }

    public function php(array $command): Process
    {
        return $this->app([
            'php',
            ...$command,
        ]);
    }

    public function app(array $command, bool $interactive = false): Process
    {
        return $this->dockerCompose([
            'exec',
            '-u',
            $this->execAppUser,
            ...$this->execAppEnvironament,
            'app',
            ...$command,
        ], false, $interactive);
    }

    public function dockerCompose(array $command, bool $silent = false, bool $interactive = false): Process
    {
        return $this->run([
            'docker-compose',
            ...$command,
        ], $silent, $interactive);
    }

    public function findAvailablePort(int $preffered, int $attempts = 20): int
    {
        $port = $preffered;
        do {
            $handle = @fsockopen('host.docker.internal', $port);
            if (! is_resource($handle)) {
                return $port;
            }
            fclose($handle);
            $port++;
        } while ($port <= $preffered + $attempts);
        throw new RuntimeException("Unable to find available port for $port + $attempts");
    }

    public function isUp(): bool
    {
        return $this->dockerCompose(['ps', '-q'], true)->getOutput() !== '';
    }
}
