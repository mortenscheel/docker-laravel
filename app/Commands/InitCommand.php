<?php

namespace App\Commands;

use App\Concerns\RendersDiffs;
use App\Facades\Process;
use App\Service\ProjectService;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Finder\Finder;

class InitCommand extends Command
{
    use RendersDiffs;

    protected $signature = 'init {--slug=             : Slug used for container prefix}
                                 {--username=laravel  : Name of the php-fpm user}
                                 {--uid=1000          : Uid of the php-fpm user}
                                 {--php=8.2           : PHP-FPM version}
                                 {--node=18           : NodeJS version}
                                 {--f|force           : Skip confirmations}';

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
        $force = $this->option('force');
        $slug = Str::slug($this->option('slug') ?: $project->pwd());
        $uid = $this->option('uid');
        $username = $this->option('username');
        $phpVersion = $this->option('php');
        $nodeVersion = $this->option('node');
        /** @var array<string, string> $replacements */
        $replacements = [
            '%slug%' => $slug,
            '%uid%' => $uid,
            '%username%' => $username,
            '%php_version%' => $phpVersion,
            '%node_version%' => $nodeVersion,
        ];
        $files = Finder::create()->in(base_path('docker-stubs'))->ignoreDotFiles(false)->files();
        $dockerFilesModified = false;
        foreach ($files as $file) {
            $contents = Str::replace(
                array_keys($replacements),
                array_values($replacements),
                $file->getContents()
            );
            if (($existing = $project->disk()->get($file->getRelativePathname())) && $existing !== $contents) {
                $this->renderDiff($existing, $contents);
                if (! $force && ! $this->confirm(sprintf('Accept these changes to %s?', $file->getRelativePathname()), true)) {
                    continue;
                }
            }
            if ($contents !== $existing) {
                $project->disk()->put($file->getRelativePathname(), $contents);
                $this->comment('+ '.$file->getRelativePathname());
                $dockerFilesModified = true;
            }
        }
        if (! $dockerFilesModified) {
            $this->comment('No changes to Docker files');
        }
        /** @var string $envOriginal */
        $envOriginal = $project->disk()->get('.env');
        $envUpdated = $this->updateOrAppendLine('DB_HOST', 'DB_HOST=db', $envOriginal);
        if (! $project->isUp()) {
            $envUpdated = $this->updateOrAppendLine('APP_PORT', 'APP_PORT='.$project->findAvailablePort(80), $envUpdated);
            $envUpdated = $this->updateOrAppendLine('FORWARD_DB_PORT', 'FORWARD_DB_PORT='.$project->findAvailablePort(3306), $envUpdated);
        }
        if ($envOriginal === $envUpdated) {
            $this->comment('No changes to .env');
        } else {
            $this->renderDiff($envOriginal, $envUpdated);
            if ($force || $this->confirm('Accept changes to .env?', true)) {
                $project->disk()->put('.env', $envUpdated);
            }
        }
        $this->info('Initialization complete');
        if ($dockerFilesModified &&
            ($force || $this->confirm('Build containers?', true))) {
            Process::dockerCompose('build')->run();
        }

        return self::SUCCESS;
    }

    private function updateOrAppendLine(string $match, string $line, string $subject): string
    {
        $pattern = sprintf('/^%s.*$/m', preg_quote($match, '/'));
        if (preg_match($pattern, $subject)) {
            return preg_replace($pattern, $line, $subject) ?? '';
        }

        return rtrim($subject).PHP_EOL.$line.PHP_EOL;
    }
}
