<?php

namespace App;

use App\Facades\ProcessFacade;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProjectManager
{
    public function hasEnvFile(): bool
    {
        return File::exists('.env');
    }

    public function isLaravelProject()
    {
        return ProcessFacade::run('grep laravel\/framework composer.json');
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
