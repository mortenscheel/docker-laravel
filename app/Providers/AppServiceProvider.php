<?php

namespace App\Providers;

use App\ProcessBuilder;
use App\Service\ProjectService;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use Intonate\TinkerZero\TinkerZeroServiceProvider;
use ReflectionClass;
use Symfony\Component\Console\Output\ConsoleOutput;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(ProcessBuilder::class, function () {
            return new ProcessBuilder(new ConsoleOutput());
        });
        /** @noinspection PhpUnhandledExceptionInspection */
        $project = $this->app->make(ProjectService::class);
        if ($project->isDockerProject() && $project->hasEnvFile()) {
            Dotenv::createMutable(getcwd())->load();
        }
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
        /** @noinspection ClassConstantCanBeUsedInspection */
        $prop->setValue($kernel, [
            ...$prop->getValue($kernel),
            'App\Commands\EditCommand',
            'App\Commands\Local\AdhocCommand',
            'Illuminate\Foundation\Console\VendorPublishCommand',
        ]);
    }
}
