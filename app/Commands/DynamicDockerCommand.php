<?php

/** @noinspection PhpMissingBreakStatementInspection */

namespace App\Commands;

use App\Service\ProcessService;
use App\Service\ProjectService;
use function array_slice;
use function count;
use Illuminate\Support\Facades\Artisan;
use LaravelZero\Framework\Commands\Command;
use ReflectionClass;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DynamicDockerCommand extends Command
{
    protected $description = 'Other commands will be proxied automatically';

    protected $name = ' ';

    private array $tokens;

    public function handle(ProcessService $process, ProjectService $project): int
    {
        if (! $project->isDockerProject()) {
            $this->error('No docker-compose environment detected');

            return self::FAILURE;
        }
        // Local docker-compose commands
        switch ($this->tokens[0]) {
            case 'build':
            case 'convert':
            case 'cp':
            case 'create':
            case 'down':
            case 'events':
            case 'exec':
            case 'images':
            case 'kill':
            case 'logs':
            case 'ls':
            case 'pause':
            case 'port':
            case 'ps':
            case 'pull':
            case 'push':
            case 'restart':
            case 'rm':
            case 'run':
            case 'start':
            case 'stop':
            case 'top':
            case 'unpause':
            case 'up':
            case 'version':
                return $process->dockerCompose($this->tokens)->getExitCode();
        }
        if (! $process->isUp()) {
            $this->warn('Please start containers before running docker commands');

            return self::FAILURE;
        }
        if (empty($this->tokens)) {
            if ($project->isLaravelProject()) {
                // Call artisan list in the container
                return $process->artisan(['list'])->getExitCode();
            }

            // Call artisan list in the laravel-docker project
            return Artisan::call('list', [], $this->getOutput());
        }
        if ($this->tokens[0] === 'debug') {
            return $process->withXdebug()->artisan(array_slice($this->tokens, 1))->getExitCode();
        }
        // Run as Artisan command if first token contains colon
        if (str_contains($this->tokens[0], ':')) {
            return $process->artisan($this->tokens)->getExitCode();
        }

        // Artisan commands
        switch ($this->tokens[0]) {
            case 'a':
            case 'artisan':
                return $process->artisan(array_slice($this->tokens, 1))->getExitCode();
            case 'dusk':
                return $process->withEnvironmentVariables([
                    'APP_URL' => 'http://nginx',
                    'DUSK_DRIVER_URL' => 'http://selenium:4444/wd/hub',
                ])->artisan(['dusk'])->getExitCode();
            case 'list':
            case 'migrate':
            case 'rollback':
            case 'adhoc':
            case 'test':
            case 'optimize':
            case 'env':
            case 'docs':
            case 'db':
            case 'completion':
            case 'clear-compiled':
            case 'about':
                return $process->artisan($this->tokens)->getExitCode();
        }

        // Composer
        switch ($this->tokens[0]) {
            case 'c':
            case 'composer':
                return $process->composer(array_slice($this->tokens, 1))->getExitCode();
            case 'cr':
                return $process->composer(['require', ...array_slice($this->tokens, 1)])->getExitCode();
            case 'cda':
                return $process->composer(['dump-autoload', ...array_slice($this->tokens, 1)])->getExitCode();
        }

        // Bash / ZSH
        switch ($this->tokens[0]) {
            case 'root':
            case 'root-shell':
                $process = $process->asUser('root');
                if (count($this->tokens) === 1) {
                    return $process->interactive()->app(['bash'])->getExitCode();
                }

                return $process->app(['bash', '-c', implode(' ', array_slice($this->tokens, 1))])->getExitCode();
            case 'shell':
            case 'zsh':
                if (count($this->tokens) === 1) {
                    return $process->interactive()->app(['zsh'], true)->getExitCode();
                }

                return $process->app(['zsh', '-c', implode(' ', array_slice($this->tokens, 1))])->getExitCode();
            case 'bash':
                if (count($this->tokens) === 1) {
                    return $process->interactive()->app($this->tokens)->getExitCode();
                }

                return $process->app(['bash', '-c', implode(' ', array_slice($this->tokens, 1))])->getExitCode();
            default:
                // Fallback to running as bash command
                return $process->app(['bash', '-c', implode(' ', $this->tokens)])->getExitCode();
        }
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $reflection = new ReflectionClass($input);
        if ($reflection->hasProperty('tokens')) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $prop = $reflection->getProperty('tokens');
            $prop->setAccessible(true);
            $this->tokens = $prop->getValue($input);

            return parent::run(new ArgvInput([]), $output);
        }

        return parent::run($input, $output);
    }
}
