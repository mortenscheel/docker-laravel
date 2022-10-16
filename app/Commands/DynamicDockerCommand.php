<?php

namespace App\Commands;

use App\Service\ProcessService;
use App\Service\ProjectService;
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
        if (empty($this->tokens)) {
            if ($project->isLaravelProject()) {
                // Call artisan list in the container
                return $process->artisan(['list'])->getExitCode();
            }
            // Call artisan list in the laravel-docker project
            return Artisan::call('list', [], $this->getOutput());
        }
        // Run as Artisan command if first token contains colon
        if (str_contains($this->tokens[0], ':')) {
            return $process->artisan($this->tokens)->getExitCode();
        }
        switch ($this->tokens[0]) {
            // Native docker-compose commands
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
                // Artisan commands
            case 'a':
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'artisan':
                array_shift($this->tokens);
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
                /* Composer */
            case 'c':
            case 'composer':
                array_shift($this->tokens);

                return $process->composer($this->tokens)->getExitCode();
            case 'cr':
                array_shift($this->tokens);

                return $process->composer(['require', ...$this->tokens])->getExitCode();
            case 'cda':
                array_shift($this->tokens);

                return $process->composer(['dump-autoload', ...$this->tokens])->getExitCode();
            case 'root':
                array_shift($this->tokens);

                return $process->appRoot(['bash', '-c', implode(' ', $this->tokens)])->getExitCode();
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'bash':
                array_shift($this->tokens);
            default:
                // Fallback to running as bash command
                return $process->app(['bash', '-c', implode(' ', $this->tokens)])->getExitCode();
        }
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $reflection = new ReflectionClass($input);
        /** @noinspection PhpUnhandledExceptionInspection */
        $prop = $reflection->getProperty('tokens');
        $prop->setAccessible(true);
        $this->tokens = $prop->getValue($input);

        return parent::run(new ArgvInput([]), $output);
    }
}
