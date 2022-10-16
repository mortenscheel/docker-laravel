<?php

namespace App\Service;

use function is_string;
use Symfony\Component\Process\Process;

class ProcessService
{
    public function create(string|array $command): Process
    {
        if (is_string($command)) {
            return Process::fromShellCommandline($command);
        }

        return new Process($command);
    }

    public function run(string|array $command, bool $captureOutput = true, ?float $timeout = null): Process
    {
        $process = $this->create($command);
        $process->setTimeout($timeout)->setTty(! $captureOutput);
        $process->run();

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

    public function app(array $command): Process
    {
        return $this->dockerCompose([
            'docker-compose',
            'exec',
            'app',
            ...$command,
        ]);
    }

    public function dockerCompose(array $command): Process
    {
        return $this->run([
            'docker-compose',
            ...$command,
        ], false);
    }

    public function appRoot(array $command): Process
    {
        return $this->dockerCompose([
            'exec',
            '-u',
            'root',
            'app',
            ...$command,
        ]);
    }
}
