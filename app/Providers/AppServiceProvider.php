<?php

namespace App\Providers;

use App\ProcessManager;
use App\ProjectManager;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Support\ServiceProvider;
use Intonate\TinkerZero\TinkerZeroServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind('process-manager', ProcessManager::class);
        $this->app->bind('project-manager', ProjectManager::class);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->environment('development')) {
            $this->app->register(TinkerZeroServiceProvider::class);
            $this->app->register(IdeHelperServiceProvider::class);
        }
    }
}
