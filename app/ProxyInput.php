<?php

namespace App;

use function array_slice;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

class ProxyInput extends ArgvInput
{
    public array $proxyTokens;

    public function __construct(array $argv = null, InputDefinition $definition = null)
    {
        $argv ??= $_SERVER['argv'] ?? [];
        $helpTokens = [];
        foreach (['--help', '-h'] as $item) {
            if (($i = array_search($item, $argv, true)) !== false) {
                $helpTokens[] = $item;
                unset($argv[$i]);
            }
        }
        $this->proxyTokens = array_merge(array_slice($argv, 1), $helpTokens);
        parent::__construct($argv, $definition);
    }
}
