<?php

namespace App\Commands;

use App\Concerns\RendersDiffs;
use App\Service\ProjectService;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
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

    public function handle(ProjectService $project): int
    {
        if (! $project->isLaravelProject()) {
            $this->error('No laravel project detected');

            return self::FAILURE;
        }
        if (! $project->hasEnvFile()) {
            $this->error('No .env file detected');

            return self::FAILURE;
        }
        $slug = $this->option('slug') ?: $this->ask('Select a slug', Str::slug($project->pwd()));
        if (! $slug) {
            $this->error('Slug cannot be empty');

            return self::FAILURE;
        }
        $slug = Str::slug($slug);
        $uid = $this->option('uid');
        $username = $this->option('username');
        $phpVersion = $this->option('php');
        $nodeVersion = $this->option('node');
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
            if (($existing = $project->disk()->get($file->getRelativePathname())) && $existing !== $contents) {
                $this->renderDiff($existing, $contents);
                if (! $this->confirm(sprintf('Accept these changes to %s?', $file->getRelativePathname()))) {
                    continue;
                }
            }
            if ($contents !== $existing) {
                $project->disk()->put($file->getRelativePathname(), $contents);
                $this->comment('+ '.$file->getRelativePathname());
            }
        }
        $envOriginal = $project->disk()->get('.env');
        $envModified = preg_replace(['/^DB_HOST=.*$/m'], ['DB_HOST=db'], $envOriginal);
        if ($envOriginal === $envModified) {
            $this->info('No changes to .env');
        } else {
            $this->renderDiff($envOriginal, $envModified);
            if ($this->confirm('Accept changes to .env?')) {
                $project->disk()->put('.env', $envModified);
            }
        }
        $this->info('Initialization complete');

        return self::SUCCESS;
    }
}
