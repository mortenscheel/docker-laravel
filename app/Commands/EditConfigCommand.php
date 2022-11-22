<?php

namespace App\Commands;

use App\Facades\Process;
use App\LocalEnvironment;
use LaravelZero\Framework\Commands\Command;

class EditConfigCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'config:edit';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Edit config file';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(LocalEnvironment $env)
    {
        return Process::command([$env->getEditorBinary(), $env->getConfigPath()])->getExitCode();
    }
}
