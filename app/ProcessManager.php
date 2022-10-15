<?php

namespace App;

use Symfony\Component\Process\Process;

class ProcessManager
{
    public function run(string $command, ?callable $onOutput = null): Process
    {
        $process = Process::fromShellCommandline($command);
        $process->run($onOutput);

        return $process;
    }
}
