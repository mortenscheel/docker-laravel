<?php

namespace App\Commands;

use Illuminate\Support\Facades\Artisan;
use LaravelZero\Framework\Commands\Command;
use Process;
use ReflectionClass;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DynamicDockerCommand extends Command
{
    protected $description = 'Other commands will be proxied automatically';

    protected $name = ' ';

    private array $tokens;

    public function handle()
    {
        if (empty($this->tokens)) {
            return Artisan::call('list', [], $this->getOutput());
        }
        // Run as Artisan command if first token contains colon
        if (str_contains($this->tokens[0], ':')) {
            return $this->runProcess([
                ...explode(' ', 'docker-compose exec app php artisan'),
                ...$this->tokens,
            ]);
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
                return $this->runProcess([
                    'docker-compose',
                    ...$this->tokens,
                ]);
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
                return $this->runArtisan($this->tokens);
                // Composer
            case 'c':
            case 'composer':
                array_shift($this->tokens);

                return $this->runComposer($this->tokens);
            case 'cr':
                array_shift($this->tokens);

                return $this->runComposer(['require', ...$this->tokens]);
            case 'cda':
                array_shift($this->tokens);

                return $this->runComposer(['dump-autoload', ...$this->tokens]);
            case 'root':
                array_shift($this->tokens);

                return $this->runExecAppAsRoot(['bash', '-c', implode(' ', $this->tokens)]);
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'bash':
                array_shift($this->tokens);
            default:
                // Fallback to running as bash command
                return $this->runExecApp(['bash', '-c', implode(' ', $this->tokens)]);
        }
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $reflection = new ReflectionClass($input);
        $prop = $reflection->getProperty('tokens');
        $prop->setAccessible(true);
        $this->tokens = $prop->getValue($input);

        return parent::run(new ArgvInput([]), $output);
    }

    private function runComposer(array $command)
    {
        return $this->runExecApp([
            'composer',
            ...$command,
        ]);
    }

    private function runArtisan(array $command)
    {
        return $this->runPhp([
            'artisan',
            ...$command,
        ]);
    }

    private function runPhp(array $command)
    {
        return $this->runExecApp([
            'php',
            ...$command,
        ]);
    }

    private function runExecAppAsRoot(array $command)
    {
        return $this->runProcess([
            'docker-compose',
            'exec',
            '-u',
            'root',
            'app',
            ...$command,
        ]);
    }

    private function runExecApp(array $command)
    {
        return $this->runProcess([
            'docker-compose',
            'exec',
            'app',
            ...$command,
        ]);
    }

    private function runProcess(array $command)
    {
        return Process::run($command, $this->getOutput())->isSuccessful();
    }
}
