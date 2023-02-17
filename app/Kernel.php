<?php

namespace App;

class Kernel extends \LaravelZero\Framework\Kernel
{
    /**
     * @param  \Symfony\Component\Console\Input\ArgvInput  $input
     * @param $output
     * @return int
     */
    public function handle($input, $output = null)
    {
        return parent::handle(new ProxyInput, $output);
    }
}
