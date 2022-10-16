<?php

namespace App\Providers;

use App\Service\ProcessService;
use App\Service\ProjectService;
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
        $this->app->bind('process-manager', ProcessService::class);
        $this->app->bind('project-manager', ProjectService::class);
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
        }
    }
}
