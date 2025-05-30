<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

use function array_slice;
use function count;
use function in_array;

/**
 * The purpose of ProxyInput is to
 * 1: Make all CLI arguments available to DefaultCommand. Some would otherwise be consumed by Symfony.
 * 2: Control whether Symfony invokes DefaultCommand or a native Docker-Laravel command.
 */
class ProxyInput extends ArgvInput
{
    /** @var array<int, string> */
    public array $proxyTokens;

    public bool $proxy = false;

    /** @var array<int, string> */
    private array $nativeTokens;

    /**
     * @param  array|string[]|null  $args
     */
    public function __construct(?array $args = null, ?InputDefinition $definition = null)
    {
        $args ??= $_SERVER['argv'] ?? [];
        $this->processTokens($args);
        parent::__construct($this->nativeTokens, $definition);
    }

    /**
     * @param  array|string[]  $args
     */
    private function processTokens(array $args): void
    {
        $this->nativeTokens = $args;
        $this->proxyTokens = array_slice($args, 1);
        $this->proxy = true;
        if (count($args) === 2 && in_array($args[1], ['--version', '-v'])) {
            // Don't proxy --version if it's the only argument
        } elseif (count($args) > 1 && str_starts_with($args[1], 'app:')) {
            // Don't proxy laravel-zero app:* commands
        } elseif (count($args) > 1 && in_array($args[1], ['config:show', 'config:edit', 'init'])) {
            // Don't proxy Docker Laravel commands
        } elseif (count($args) > 2 && in_array($args[1], ['--local', '-l'])) {
            // Don't proxy if first argument is --local
            $this->nativeTokens = [$this->nativeTokens[0], ...array_slice($this->nativeTokens, 2)];
        } else {
            $this->nativeTokens = [$args[0]];
        }
    }
}
