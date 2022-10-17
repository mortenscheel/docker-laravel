<?php

namespace App\Service;

use App\Facades\Process;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProjectService
{
    public function hasEnvFile(): bool
    {
        return File::exists('.env');
    }

    public function isLaravelProject(): bool
    {
        return $this->process->run('grep laravel\/framework composer.json')->isSuccessful();
    }

    public function isDockerProject(): bool
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

    public function isUp(): bool
    {
        return Process::silent()->dockerCompose('ps -q')->run()->getOutput() !== '';
    }

    public function findAvailablePort(int $preffered, int $attempts = 20): int
    {
        $port = $preffered;
        $host = $_ENV['DOCKER_LARAVEL_HOST'] ?? 'host.docker.internal';
        do {
            $handle = @fsockopen($host, $port);
            if (! is_resource($handle)) {
                return $port;
            }
            fclose($handle);
            $port++;
        } while ($port <= $preffered + $attempts);
        throw new RuntimeException("Unable to find available port for $port + $attempts");
    }
}
