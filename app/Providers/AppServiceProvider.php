<?php

namespace App\Providers;

use App\Commands\Local\AdhocCommand;
use App\Service\ProcessService;
use App\Service\ProjectService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use Intonate\TinkerZero\TinkerZeroServiceProvider;
use ReflectionClass;

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

    public function register()
    {
        if ($this->app->environment('development')) {
            $this->app->register(TinkerZeroServiceProvider::class);
        } else {
            $this->excludeDevelopmentCommands();
        }
    }

    private function excludeDevelopmentCommands()
    {
        /** @var \LaravelZero\Framework\Kernel $kernel */
        $kernel = app(Kernel::class);
        $reflection = new ReflectionClass($kernel);
        $prop = $reflection->getProperty('developmentOnlyCommands');
        $prop->setAccessible(true);
        $prop->setValue($kernel, [...$prop->getValue($kernel), AdhocCommand::class]);
    }
}
