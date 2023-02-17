<?php

namespace App;

use function array_slice;
use function count;
use function in_array;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

class ProxyInput extends ArgvInput
{
    public array $commandTokens;

    public function __construct(array $args = null, InputDefinition $definition = null)
    {
        $args ??= $_SERVER['argv'] ?? [];
        if (count($args) === 2 && in_array($args[1], ['--version', '-v'])) {
            // Don't proxy --version if it's the only argument
            parent::__construct($args, $definition);
        } elseif (count($args) > 1 && str_starts_with($args[1], 'app:')) {
            // Allow laravel-zero app:* commands
            parent::__construct($args, $definition);
        } else {
            $this->commandTokens = array_slice($args, 1);
            parent::__construct([$args[0]], $definition);
        }
    }
}
