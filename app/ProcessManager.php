<?php

namespace App;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use function is_string;

class ProcessManager
{
    public function run(string|array $command, OutputInterface $output = null, ?float $timeout = null): Process
    {
        if (is_string($command)) {
            $process = Process::fromShellCommandline($command);
        } else {
            $process = new Process($command);
        }
        $process->setTimeout($timeout)->setTty(true);
        if ($output) {
            $callback = fn ($type, $buffer) => $output->write($buffer);
        } else {
            $callback = null;
        }
        $process->run($callback);

        return $process;
    }
}
