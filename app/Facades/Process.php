<?php

namespace App\Facades;

use App\ProcessBuilder;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ProcessBuilder command(array|string $command)
 * @method static ProcessBuilder artisan(array|string $command)
 * @method static ProcessBuilder php(array|string $command)
 * @method static ProcessBuilder app(array|string $command)
 * @method static ProcessBuilder dockerCompose(array|string $command)
 * @method static ProcessBuilder composer(array|string $command)
 * @method static ProcessBuilder user(string $username)
 * @method static ProcessBuilder setEnvironement(array $environment)
 * @method static ProcessBuilder mergeEnvironement(array $environment)
 * @method static ProcessBuilder interactive(bool $interactive = true)
 * @method static ProcessBuilder silent(bool $silent = true)
 * @method static ProcessBuilder debug(bool $debug = true)
 * @method static ProcessBuilder xdebug()
 * @method static ProcessBuilder timeout(?float $timeout)
 * @method static ProcessBuilder make()
 * @method static int getExitCode()
 * @method static string getOutput()
 */
class Process extends Facade
{
    protected static $cached = false;

    protected static function getFacadeAccessor(): string
    {
        return ProcessBuilder::class;
    }
}
