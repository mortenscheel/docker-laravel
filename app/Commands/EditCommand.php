<?php

namespace App\Commands;

use App\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class EditCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'app:edit';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Edit the docker-laravel project';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        return Process::run(['code', base_path()])->getExitCode();
    }
}
