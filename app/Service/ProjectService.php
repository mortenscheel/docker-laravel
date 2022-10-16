<?php

namespace App\Service;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProjectService
{
    public function __construct(private ProcessService $process)
    {
    }

    public function hasEnvFile(): bool
    {
        return File::exists('.env');
    }

    public function isLaravelProject()
    {
        return $this->process->run('grep laravel\/framework composer.json')->isSuccessful();
    }

    public function isDockerProject()
    {
        return File::exists('docker-compose.yml') &&
            File::isDirectory('docker');
    }

    public function pwd(bool $absolute = false): string
    {
        $dir = $this->disk()->path('');

        return $absolute ? $dir : basename($dir);
    }

    /** @noinspection PhpIncompatibleReturnTypeInspection */
    public function disk(): FilesystemAdapter
    {
        return Storage::disk('project');
    }
}
