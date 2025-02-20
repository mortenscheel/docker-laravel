<?php

namespace App;

use App\Facades\Process;
use Illuminate\Support\Arr;
use JsonException;
use RuntimeException;

class LocalEnvironment
{
    public function getConfigPath(): string
    {
        /** @var string $home */
        $home = Arr::get($_SERVER, 'HOME', '~');

        return rtrim($home, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.docker-laravel.json';
    }

    public function hasConfig(): bool
    {
        return file_exists($this->getConfigPath());
    }

    public function getConfig(string $key = null, mixed $default = null): mixed
    {
        if (($path = $this->getConfigPath()) && file_exists($path)) {
            try {
                /** @var string $json */
                $json = file_get_contents($path);
                /** @var array<int, mixed> $data */
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                if (! $key) {
                    return $data;
                }

                return Arr::get($data, $key, $default);
            } catch (JsonException) {
            }
        }

        return $default;
    }

    /**
     * @return array|\ArrayAccess|mixed
     */
    public function getEnvironment(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_ENV;
        }

        return Arr::get($_ENV, $key, $default);
    }

    public function debug(): bool
    {
        return (bool) \getenv('DL_DEBUG');
    }

    public function shouldForceTty(): bool
    {
        return (bool) $this->getEnvironment('DL_INTERACTIVE', false);
    }

    public function getEditorBinary(): string
    {
        $editor = Arr::get($_ENV, 'VISUAL', Arr::get($_ENV, 'EDITOR', 'vi'));
        $process = Process::command(['which', $editor])->silent()->run();
        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }
        throw new RuntimeException('No editor binary found');
    }
}
