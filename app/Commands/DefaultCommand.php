<?php

/** @noinspection PhpMissingBreakStatementInspection */

namespace App\Commands;

use App\Facades\Process;
use App\LocalEnvironment;
use App\ProcessBuilder;
use App\Service\ProjectService;
use function array_slice;
use function count;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use function is_array;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DefaultCommand extends Command
{
    protected $description = 'Default command';

    protected $name = 'default';

    private array $tokens;

    private LocalEnvironment $localEnvironment;

    public function __construct()
    {
        parent::__construct();
        $this->localEnvironment = app(LocalEnvironment::class);
    }

    public function handle(ProjectService $project): int
    {
        if (! $project->isDockerProject()) {
            $this->error('No docker-compose environment detected');

            return self::FAILURE;
        }
        $process = Process::make();
        if (empty($this->tokens)) {
            if ($project->isLaravelProject()) {
                if (! $project->isUp()) {
                    $this->warn('Please start containers before running docker commands');

                    return self::FAILURE;
                }
                // Call artisan list in the container
                return $process->artisan(['list'])->getExitCode();
            }
            // Call artisan list in the laravel-docker project
            return Artisan::call('list', [], $this->getOutput());
        }
        // Local docker-compose commands
        switch ($this->tokens[0]) {
            case 'build':
            case 'pull':
            case 'up':
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
            case 'push':
            case 'restart':
            case 'rm':
            case 'run':
            case 'start':
            case 'stop':
            case 'top':
            case 'unpause':
            case 'version':
                return $process->interactive()->dockerCompose($this->tokens)->getExitCode();
        }
        if (! $project->isUp()) {
            $this->warn('Please start containers before running docker commands');

            return self::FAILURE;
        }
        $this->processConfig();

        return $this->processTokens($this->tokens);
    }

    private function processTokens(array $tokens, ProcessBuilder $process = null): int
    {
        $process = $process ?? Process::make();
        if ($tokens[0] === '--tty') {
            $tokens = array_slice($tokens, 1);
        }
        if ($tokens[0] === 'debug') {
            return $process->xdebug()->artisan(array_slice($tokens, 1))->getExitCode();
        }
        if ($tokens[0] === 'kill-php-fpm') {
            return $this->processTokens(['root', 'kill', '-USR2', '1'], Process::silent());
        }
        if ($tokens[0] === 'xdebug') {
            $loaded = Process::app([
                'grep',
                '-q',
                '^zend_extension',
                '/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini',
            ])->silent()->run()->isSuccessful();
            switch ($tokens[1] ?? 'status') {
                case 'on':
                case '1':
                case 'start':
                    if ($loaded) {
                        $this->info('Xdebug was already loaded');

                        return self::SUCCESS;
                    }
                    $success = $process->user('root')->app([
                        'sed',
                        '-i',
                        's/^#zend_extension=xdebug/zend_extension=xdebug/g',
                        '/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini',
                    ])->silent()->run()->isSuccessful();
                    if ($success) {
                        $this->info('Xdebug loaded');

                        return $this->processTokens(['kill-php-fpm'], Process::silent());
                    }
                    $this->error('Failed to load xdebug');

                    return self::FAILURE;
                case 'off':
                case '0':
                case 'stop':
                    if (! $loaded) {
                        $this->info('Xdebug was already unloaded');

                        return self::SUCCESS;
                    }
                    $success = Process::user('root')->app([
                        'sed',
                        '-i',
                        's/^zend_extension=xdebug/#zend_extension=xdebug/g',
                        '/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini',
                    ])->silent()->run()->isSuccessful();
                    if ($success) {
                        $this->info('Xdebug unloaded');

                        return $this->processTokens(['kill-php-fpm'], Process::silent());
                    }
                    $this->error('Failed to unload xdebug');

                    return self::FAILURE;
                case 'status':
                    $this->info('Xdebug is '.($loaded ? 'loaded' : 'not loaded'));

                    return self::SUCCESS;
            }
        }
        if ($tokens[0] === 'mysql-tail') {
            return $process->dockerCompose(['exec', 'db', 'bash', '-c', 'tail -f /tmp/*.log'])->getExitCode();
        }
        // Run as Artisan command if first token contains colon
        if (str_contains($tokens[0], ':')) {
            return $process->artisan($tokens)->getExitCode();
        }
        // Artisan commands
        switch ($tokens[0]) {
            case 'a':
            case 'artisan':
                return $process->artisan(array_slice($tokens, 1))->getExitCode();
            case 'dusk':
                return $process->setEnvironment([
                    'APP_URL' => 'http://nginx',
                    'DUSK_DRIVER_URL' => 'http://selenium:4444/wd/hub',
                ])->artisan('dusk')->getExitCode();
            case 'list':
            case 'help':
            case 'migrate':
            case 'rollback':
            case 'adhoc':
            case 'test':
            case 'optimize':
            case 'env':
            case 'docs':
            case 'db':
            case 'tinker':
                $process->interactive();
            case 'completion':
            case 'clear-compiled':
            case 'about':
                return $process->artisan($tokens)->getExitCode();
        }
        // Composer
        switch ($tokens[0]) {
            case 'c':
            case 'composer':
                return $process->composer(array_slice($tokens, 1))->getExitCode();
        }
        // Bash / ZSH
        $process->interactive();
        switch ($tokens[0]) {
            case 'root':
            case 'root-shell':
                $process->user('root');
                if (count($tokens) === 1) {
                    return $process->app(['bash'])->getExitCode();
                }

                return $process->app(['bash', '-i', '-c', implode(' ', array_slice($tokens, 1))])->getExitCode();
            case 'shell':
            case 'zsh':
                if (count($tokens) === 1) {
                    return $process->app(['zsh'])->getExitCode();
                }

                return $process->app(['zsh', '-i', '-c', implode(' ', array_slice($tokens, 1))])->getExitCode();
            case 'bash':
                if (count($tokens) === 1) {
                    return $process->app($tokens)->getExitCode();
                }

                return $process->app(['bash', '-c', implode(' ', array_slice($tokens, 1))])->getExitCode();
            case 'tail':
                return $process->app(['bash', '-c', 'tail -f storage/logs/*.log'])->getExitCode();
            case 'tail-general':
                $turnOn = Process::query('SET GLOBAL general_log=1;')->silent()->run();
                if ($turnOn->isSuccessful()) {
                    return $process->command(['tail', '-f', 'docker/mysql/logs/general.log'])->getExitCode();
                }

                return self::FAILURE;
            case 'tail-slow':
                $turnOn = Process::query('SET GLOBAL slow_query_log=1;')->silent()->run();
                if ($turnOn->isSuccessful()) {
                    return $process->command(['tail', '-f', 'docker/mysql/logs/slow.log'])->getExitCode();
                }

                return self::FAILURE;
            default:
                // Fallback to running as bash command
                return $process->app(['bash', '-c', implode(' ', $tokens)])->getExitCode();
        }
    }

    /**
     * @param  \App\ProxyInput  $input
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->tokens = $input->proxyTokens;

        return parent::run($input, $output);
    }

    private function processConfig(): void
    {
        $aliases = $this->localEnvironment->getConfig('aliases');
        if ($alias = Arr::get($aliases, $this->tokens[0])) {
            if ($this->localEnvironment->debug()) {
                fwrite(STDERR, sprintf("Alias '%s' was resolved to '%s'\n", $this->tokens[0], $alias));
            }
            if (! is_array($alias)) {
                $alias = collect(explode(' ', $alias))->filter()->map(fn ($token) => trim($token))->toArray();
            }
            $this->tokens = [...$alias, ...array_slice($this->tokens, 1)];
        }
    }
}
