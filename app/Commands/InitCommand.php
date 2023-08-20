<?php

namespace App\Commands;

use App\Concerns\RendersDiffs;
use App\Facades\Process;
use App\Service\ProjectService;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

use function array_key_exists;

class InitCommand extends Command
{
    use RendersDiffs;

    protected $signature = 'init {--slug=             : Slug used for container prefix}
                                 {--php=8.2           : PHP-FPM version}
                                 {--node=20           : NodeJS version}
                                 {--redis             : Include Redis service}
                                 {--meilisearch       : Include Meilisearch service}
                                 {--selenium          : Include Selenium service}
                                 {--update            : Update existing environment}
                                 {--f|force           : Skip confirmations}';

    public function __construct(
        private ProjectService $project
    ) {
        parent::__construct();
    }

    protected $description = 'Initialize Docker environment';

    public function handle(): int
    {
        if (! $this->project->isLaravelProject()) {
            $this->error('No laravel project detected');

            return self::FAILURE;
        }
        if (! $this->project->hasEnvFile()) {
            $this->error('No .env file detected');

            return self::FAILURE;
        }
        $force = $this->option('force');
        if ($this->option('update')) {
            [
                $slug,
                $phpVersion,
                $nodeVersion,
                $redis,
                $meilisearch,
                $selenium
            ] = $this->detectCurrentConfig();
        } else {
            $slug = Str::slug($this->option('slug') ?: $this->project->pwd());
            $phpVersion = (string) $this->option('php');
            $nodeVersion = (string) $this->option('node');
            $redis = (bool) $this->option('redis');
            $meilisearch = (bool) $this->option('meilisearch');
            $selenium = (bool) $this->option('selenium');
        }
        $dockerComposeConfig = $this->generateDockerComposeConfig(
            $slug,
            $phpVersion,
            $nodeVersion,
            $redis,
            $meilisearch,
            $selenium
        );
        $files = Finder::create()->in(base_path('docker-stubs'))->ignoreDotFiles(false)->files();
        $dockerFilesModified = false;
        foreach ($files as $file) {
            $contents = $file->getContents();
            if ($file->getFilename() === 'docker-compose.yml') {
                $contents = $dockerComposeConfig;
            }
            if (($existing = $this->project->disk()->get($file->getRelativePathname())) && $existing !== $contents) {
                $this->renderDiff($existing, $contents);
                if (! $force && ! $this->confirm(sprintf('Accept these changes to %s?', $file->getRelativePathname()),
                    true)) {
                    continue;
                }
            }
            if ($contents !== $existing) {
                $this->project->disk()->put($file->getRelativePathname(), $contents);
                $this->comment('+ '.$file->getRelativePathname());
                $dockerFilesModified = true;
            }
        }
        if (! $dockerFilesModified) {
            $this->comment('No changes to Docker files');
        }
        /** @var string $envOriginal */
        $envOriginal = $this->project->disk()->get('.env');
        $envUpdates = [
            'DB_HOST' => 'db',
            'WWWUSER' => rtrim(Process::silent()->run('id -u')->getOutput()),
        ];
        if ($redis) {
            $envUpdates = [
                ...$envUpdates,
                'REDIS_HOST' => 'redis',
                'QUEUE_CONNECTION' => 'redis',
                'CACHE_DRIVER' => 'redis',
                'SESSION_DRIVER' => 'redis',
            ];
        }
        if ($meilisearch) {
            $envUpdates = [
                ...$envUpdates,
                'SCOUT_DRIVER' => 'meilisearch',
                'MEILISEARCH_HOST' => 'http://meilisearch:7700',
            ];
        }
        if (! $this->project->isUp()) {
            $envUpdates = [
                ...$envUpdates,
                'APP_PORT' => $this->project->findAvailablePort(80),
                'FORWARD_DB_PORT' => $this->project->findAvailablePort(3306),
            ];
        }
        $envUpdated = $this->updateEnvFile($envOriginal, $envUpdates);
        if ($envOriginal === $envUpdated) {
            $this->comment('No changes to .env');
        } else {
            $this->renderDiff($envOriginal, $envUpdated);
            if ($force || $this->confirm('Accept changes to .env?', true)) {
                $this->project->disk()->put('.env', $envUpdated);
            }
        }
        $this->info('Initialization complete');
        if ($dockerFilesModified &&
            ($force || $this->confirm('Build containers?'))) {
            Process::dockerCompose('build')->run();
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string|int>  $searchReplace
     */
    private function updateEnvFile(string $content, array $searchReplace): string
    {
        foreach ($searchReplace as $match => $value) {
            $line = "$match=$value";
            $pattern = sprintf('/^%s.*$/m', preg_quote($match, '/'));
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $line, $content) ?? '';
            } else {
                $content = rtrim($content).PHP_EOL.$line.PHP_EOL;
            }
        }

        return $content;
    }

    private function generateDockerComposeConfig(
        string $slug,
        string $phpVersion,
        string $nodeVersion,
        bool $redis,
        bool $meilisearch,
        bool $selenium
    ): string {
        $config = [
            'version' => '3.7',
            'services' => [
                'app' => [
                    'container_name' => "{$slug}_app",
                    'restart' => 'unless-stopped',
                    'build' => [
                        'args' => [
                            'PHP_VERSION' => $phpVersion,
                            'NODE_VERSION' => $nodeVersion,
                            'ENVIRONMENT' => 'development',
                        ],
                        'context' => './docker/php-fpm',
                    ],
                    'environment' => [
                        'WWWUSER' => '${WWWUSER:-1000}',
                        'XDEBUG_MODE' => '${DOCKER_XDEBUG_MODE:-debug}',
                        'XDEBUG_CONFIG' => '${DOCKER_XDEBUG_CONFIG:-client_host=host.docker.internal}',
                    ],
                    'extra_hosts' => [
                        'host.docker.internal:host-gateway',
                    ],
                    'volumes' => [
                        './:/var/www/html',
                        '${COMPOSER_HOME:-$HOME/.composer}:/var/www/.composer',
                        './storage/app/code-coverage:/opt/phpstorm-coverage',
                    ],
                    'depends_on' => [
                        'db',
                        'nginx',
                    ],
                    'networks' => [
                        'app-network',
                    ],
                ],
                'db' => [
                    'image' => 'mysql/mysql-server:8.0',
                    'container_name' => "{$slug}_db",
                    'restart' => 'unless-stopped',
                    'environment' => [
                        'MYSQL_DATABASE' => '${DB_DATABASE}',
                        'MYSQL_TEST_DATABASE' => '${DB_TEST_DATABASE:-${DB_DATABASE}_test}',
                        'MYSQL_ROOT_PASSWORD' => '${DB_PASSWORD}',
                        'MYSQL_PASSWORD' => '${DB_PASSWORD}',
                        'MYSQL_USER' => '${DB_USERNAME}',
                        'MYSQL_ROOT_HOST' => '%',
                        'MYSQL_ALLOW_EMPTY_PASSWORD' => 'yes',
                        'SERVICE_TAGS' => 'dev',
                        'SERVICE_NAME' => 'mysql',
                        'TZ' => '${TZ:-UTC}',
                    ],
                    'ports' => [
                        '${FORWARD_DB_PORT:-3306}:3306',
                    ],
                    'volumes' => [
                        'mysql-data:/var/lib/mysql',
                        './docker/mysql/docker-entrypoint-initdb.d:/docker-entrypoint-initdb.d',
                        './docker/mysql/my.cnf:/etc/my.cnf',
                        './docker/mysql/my.cnf.d/:/etc/my.cnf.d/',
                    ],
                    'networks' => [
                        'app-network',
                    ],
                ],
                'nginx' => [
                    'image' => 'nginx:alpine',
                    'container_name' => "{$slug}_nginx",
                    'restart' => 'unless-stopped',
                    'extra_hosts' => [
                        'host.docker.internal:host-gateway',
                    ],
                    'ports' => [
                        '${APP_PORT:-80}:80',
                    ],
                    'volumes' => [
                        './:/var/www/html',
                        './docker/nginx/conf.d:/etc/nginx/conf.d',
                    ],
                    'networks' => [
                        'app-network',
                    ],
                ],
            ],
            'volumes' => [
                'mysql-data' => [
                    'driver' => 'local',
                ],
            ],
            'networks' => [
                'app-network' => [
                    'driver' => 'bridge',
                ],
            ],
        ];
        if ($selenium) {
            $config['services']['selenium'] = [
                'image' => '${SELENIUM_IMAGE:-selenium/standalone-chrome}',
                'container_name' => "{$slug}_selenium",
                'restart' => 'unless-stopped',
                'extra_hosts' => [
                    'host.docker.internal:host-gateway',
                ],
                'volumes' => [
                    '/dev/shm:/dev/shm',
                ],
                'networks' => [
                    'app-network',
                ],
            ];
            $config['services']['app']['depends_on'][] = 'selenium';
        }
        if ($redis) {
            $config['services']['redis'] = [
                'image' => 'redis:alpine',
                'container_name' => "{$slug}_redis",
                'restart' => 'unless-stopped',
                'volumes' => [
                    'redis-data:/data',
                ],
                'ports' => [
                    '${FORWARD_REDIS_PORT:-6379}:6379',
                ],
                'networks' => [
                    'app-network',
                ],
            ];
            $config['services']['redisinsight'] = [
                'image' => 'redislabs/redisinsight:latest',
                'container_name' => "{$slug}_redisinsight",
                'restart' => 'unless-stopped',
                'volumes' => [
                    'redisinsight-data:/db',
                ],
                'ports' => [
                    '${FORWARD_REDISINSIGHT_PORT:-8001}:8001',
                ],
                'networks' => [
                    'app-network',
                ],
            ];
            $config['services']['app']['depends_on'][] = 'redis';
            $config['services']['app']['depends_on'][] = 'redisinsight';
            $config['volumes']['redis-data'] = ['driver' => 'local'];
            $config['volumes']['redisinsight-data'] = ['driver' => 'local'];
        }
        if ($meilisearch) {
            $config['services']['meilisearch'] = [
                'image' => 'getmeili/meilisearch:latest',
                'container_name' => "{$slug}_meilisearch",
                'restart' => 'unless-stopped',
                'volumes' => [
                    'meilisearch-data:/data',
                ],
                'ports' => [
                    '${FORWARD_MEILISEARCH_PORT:-7700}:7700',
                ],
                'networks' => [
                    'app-network',
                ],
            ];
            $config['services']['app']['depends_on'][] = 'meilisearch';
            $config['volumes']['meilisearch-data'] = ['driver' => 'local'];
        }

        return Yaml::dump($config, 10);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: bool, 4: bool, 5: bool}
     */
    private function detectCurrentConfig(): array
    {
        $config = Yaml::parse((string) $this->project->disk()->get('docker-compose.yml'));
        $slug = Str::before($config['services']['app']['container_name'], '_app');
        $phpVersion = $config['services']['app']['build']['args']['PHP_VERSION'];
        $nodeVersion = $config['services']['app']['build']['args']['NODE_VERSION'];
        $redis = array_key_exists('redis', $config['services']);
        $meilisearch = array_key_exists('meilisearch', $config['services']);
        $selenium = array_key_exists('selenium', $config['services']);

        return [$slug, $phpVersion, $nodeVersion, $redis, $meilisearch, $selenium];
    }
}
