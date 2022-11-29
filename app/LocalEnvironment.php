<?php

namespace App;

use App\Facades\Process;
use Illuminate\Support\Arr;

class LocalEnvironment
{
    public function getConfigPath(): string
    {
        return rtrim(Arr::get($_SERVER, 'HOME', '~'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.docker-laravel.json';
    }

    public function hasConfig(): bool
    {
        return file_exists($this->getConfigPath());
    }

    /**
     * @param  string|null  $key
     * @param  mixed|null  $default
     * @return mixed
     */
    public function getConfig(string $key = null, mixed $default = null): mixed
    {
        if (($path = $this->getConfigPath()) && file_exists($path)) {
            try {
                $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (! $key) {
                    return $data;
                }

                return Arr::get($data, $key, $default);
            } catch (\JsonException) {
            }
        }

        return $default;
    }

    public function getEnvironment(string $key = null, mixed $default = null)
    {
        if ($key === null) {
            return $_ENV;
        }

        return Arr::get($_ENV, $key, $default);
    }

    public function debug(): bool
    {
        return (bool) $this->getEnvironment('DL_DEBUG', false);
    }

    public function shouldForceTty(): bool
    {
        return (bool) $this->getEnvironment('DL_INTERACTIVE');
    }

    public function getEditorBinary(): string
    {
        $editor = Arr::get($_ENV, 'VISUAL', Arr::get($_ENV, 'EDITOR', 'vi'));
        $process = Process::command(['which', $editor])->silent()->run();
        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }
        throw new \RuntimeException('No editor binary found');
    }
}
