<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Str;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\DiffOnlyOutputBuilder;

/** @mixin \Illuminate\Console\Command */
trait RendersDiffs
{
    private function renderDiff(string $from, string $to): void
    {
        foreach (explode("\n", (new Differ(new DiffOnlyOutputBuilder))->diff($from, $to)) as $line) {
            if (Str::startsWith($line, ['+++', '---', '@@ @@'])) {
                continue;
            }
            if (Str::startsWith($line, '+')) {
                $line = sprintf("\033[32m %s\033[0m", Str::after($line, '+'));
            } elseif (Str::startsWith($line, '-')) {
                $line = sprintf("\033[31m %s\033[0m", Str::after($line, '-'));
            }
            $this->getOutput()->writeln($line);
        }
    }
}
