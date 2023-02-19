<?php

namespace App\Providers;

use App\ProcessBuilder;
use App\Service\ProjectService;
use Dotenv\Dotenv;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Output\ConsoleOutput;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function boot()
    {
        $this->app->bind(ProcessBuilder::class, fn () => new ProcessBuilder(new ConsoleOutput));
        /** @noinspection PhpUnhandledExceptionInspection */
        $project = $this->app->make(ProjectService::class);
        if ($project->isDockerProject() && $project->hasEnvFile()) {
            Dotenv::createMutable(getcwd())->load();
        }
    }
}
