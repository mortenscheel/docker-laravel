<?php

namespace App\Commands;

use App\Concerns\RendersDiffs;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Project;
use Symfony\Component\Finder\Finder;

class InitCommand extends Command
{
    use RendersDiffs;

    protected $signature = 'init {--slug=}
                                 {--username=laravel}
                                 {--uid=1000}
                                 {--php=8.1}
                                 {--node=16}';

    protected $description = 'Initialize Docker environment';

    public function handle(): int
    {
        if (! $this->isValidProject()) {
            return self::FAILURE;
        }
        $slug = $this->option('slug') ?: $this->ask('Select a slug', Str::slug(Project::pwd()));
        if (! $slug) {
            $this->error('Slug cannot be empty');

            return self::FAILURE;
        }
        $slug = Str::slug($slug);
        $uid = $this->option('uid');
        $username = $this->option('username');
        $phpVersion = $this->option('php');
        $nodeVersion = $this->option('node');
        $destination = Project::disk();
        $replacements = [
            '%slug%' => $slug,
            '%uid%' => $uid,
            '%username%' => $username,
            '%php_version%' => $phpVersion,
            '%node_version%' => $nodeVersion,
        ];
        $files = Finder::create()->in(base_path('docker-stubs'))->files();
        foreach ($files as $file) {
            $contents = Str::replace(
                array_keys($replacements),
                array_values($replacements),
                $file->getContents()
            );
            if (($existing = $destination->get($file->getRelativePathname())) && $existing !== $contents) {
                $this->renderDiff($existing, $contents);
                if (! $this->confirm(sprintf('Accept these changes to %s?', $file->getRelativePathname()))) {
                    continue;
                }
            }
            if ($contents !== $existing) {
                $destination->put($file->getRelativePathname(), $contents);
                $this->comment('+ '.$file->getRelativePathname());
            }
        }
        $envOriginal = $destination->get('.env');
        $envModified = preg_replace(['/^DB_HOST=.*$/m'], ['DB_HOST=db'], $envOriginal);
        if ($envOriginal === $envModified) {
            $this->info('No changes to .env');
        } else {
            $this->renderDiff($envOriginal, $envModified);
            if ($this->confirm('Accept changes to .env?')) {
                $destination->put('.env', $envModified);
            }
        }
        $this->info('Initialization complete');

        return self::SUCCESS;
    }

    private function isValidProject()
    {
        if (! Project::isLaravelProject()) {
            $this->warn('No Laravel project detected.');

            return false;
        }
        if (! Project::hasEnvFile()) {
            $this->warn('No .env file detected.');

            return false;
        }

        return true;
    }
}
