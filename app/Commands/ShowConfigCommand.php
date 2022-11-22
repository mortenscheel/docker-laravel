<?php

namespace App\Commands;

use App\LocalEnvironment;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ShowConfigCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'config:show {--show-env : Also show environment variables}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Show local config';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(LocalEnvironment $env)
    {
        if ($env->hasConfig()) {
            dump($env->getConfig());
            if ($this->option('show-env')) {
                $this->comment('Environment variables:');
                dump($env->getEnvironment());
            }

            return self::SUCCESS;
        }
        $this->warn('No config found. Create it with config:edit');

        return self::FAILURE;
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
