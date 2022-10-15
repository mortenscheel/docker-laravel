<?php

namespace App\Concerns;

use Illuminate\Support\Str;
use SebastianBergmann\Diff\Differ;

/** @mixin \Illuminate\Console\Command */
trait RendersDiffs
{
    private function renderDiff(string $from, string $to)
    {
        foreach (explode("\n", (new Differ())->diff($from, $to)) as $line) {
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
