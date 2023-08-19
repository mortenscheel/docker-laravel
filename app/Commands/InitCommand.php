<?php

namespace App\Commands;

use App\Concerns\RendersDiffs;
use App\Facades\Process;
use App\Service\ProjectService;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class InitCommand extends Command
{
    use RendersDiffs;

    protected $signature = 'init {--slug=             : Slug used for container prefix}
                                 {--uid=1000          : UID of the php-fpm user}
                                 {--gid=1000          : GID of the php-fpm user}
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
                $uid,
                $gid,
                $phpVersion,
                $nodeVersion,
                $redis,
                $meilisearch,
                $selenium
            ] = $this->detectCurrentConfig();
        } else {
            $slug = Str::slug($this->option('slug') ?: $this->project->pwd());
            $uid = (string) $this->option('uid');
            $gid = (string) $this->option('gid');
            $phpVersion = (string) $this->option('php');
            $nodeVersion = (string) $this->option('node');
            $redis = (bool) $this->option('redis');
            $meilisearch = (bool) $this->option('meilisearch');
            $selenium = (bool) $this->option('selenium');
        }
        $dockerComposeConfig = $this->generateDockerComposeConfig(
            $slug,
            $uid,
            $gid,
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
        $envUpdated = $this->updateOrAppendLine('DB_HOST', 'DB_HOST=db', $envOriginal);
        if ($redis) {
            $envUpdated = $this->updateOrAppendLine('REDIS_HOST', 'REDIS_HOST=redis', $envUpdated);
        }
        if ($meilisearch) {
            $envUpdated = $this->updateOrAppendLine('SCOUT_DRIVER', 'SCOUT_DRIVER=meilisearch', $envUpdated);
            $envUpdated = $this->updateOrAppendLine('MEILISEARCH_HOST',
                'MEILISEARCH_HOST=http://meilisearch:7700',
                $envUpdated);

        }
        if (! $this->project->isUp()) {
            $envUpdated = $this->updateOrAppendLine('APP_PORT',
                'APP_PORT='.$this->project->findAvailablePort(80),
                $envUpdated);
            $envUpdated = $this->updateOrAppendLine('FORWARD_DB_PORT',
                'FORWARD_DB_PORT='.$this->project->findAvailablePort(3306),
                $envUpdated);
        }
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

    private function updateOrAppendLine(string $match, string $line, string $subject): string
    {
        $pattern = sprintf('/^%s.*$/m', preg_quote($match, '/'));
        if (preg_match($pattern, $subject)) {
            return preg_replace($pattern, $line, $subject) ?? '';
        }

        return rtrim($subject).PHP_EOL.$line.PHP_EOL;
    }

    private function generateDockerComposeConfig(
        string $slug,
        string $uid,
        string $gid,
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
                    'image' => "mono2990/docker-laravel:$phpVersion-dev",
                    'container_name' => "{$slug}_app",
                    'restart' => 'unless-stopped',
                    'build' => [
                        'args' => [
                            'UID' => $uid,
                            'GID' => $gid,
                            'PHP_VERSION' => $phpVersion,
                            'NODE_VERSION' => $nodeVersion,
                        ],
                        'context' => './docker/php-fpm',
                    ],
                    'user' => 'www-data',
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
                        './docker/mysql/default.cnf:/etc/my.cnf.d/00-default.cnf',
                        './docker/mysql/optimize.cnf:/etc/my.cnf.d/01-optimize.cnf',
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
            $config['services']['app']['depends_on'][] = 'redis';
            $config['volumes']['redis-data'] = ['driver' => 'local'];
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
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: bool, 6: bool, 7: bool}
     */
    private function detectCurrentConfig(): array
    {
        $config = Yaml::parse((string) $this->project->disk()->get('docker-compose.yml'));
        $slug = Str::before($config['services']['app']['container_name'], '_app');
        $uid = $config['services']['app']['build']['args']['UID'];
        $gid = $config['services']['app']['build']['args']['GID'];
        $phpVersion = $config['services']['app']['build']['args']['PHP_VERSION'];
        $nodeVersion = $config['services']['app']['build']['args']['NODE_VERSION'];
        $redis = \array_key_exists('redis', $config['services']);
        $meilisearch = \array_key_exists('meilisearch', $config['services']);
        $selenium = \array_key_exists('selenium', $config['services']);

        return [$slug, $uid, $gid, $phpVersion, $nodeVersion, $redis, $meilisearch, $selenium];
    }
}
