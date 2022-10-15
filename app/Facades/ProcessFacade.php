<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;
use Symfony\Component\Process\Process;

/**
 * @method static Process run(string $command, ?callable $onOutput = null)
 */
class ProcessFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'process-manager';
    }
}
