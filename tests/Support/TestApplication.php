<?php

namespace Vivalto\Connect\Tests\Support;

use Illuminate\Container\Container;

/**
 * A minimal container that satisfies the parts of the Laravel application
 * contract that Illuminate\Console\Command touches.
 *
 * The SDK tests do not boot a full Laravel application, so this stand-in
 * provides the few methods the console command calls on $this->laravel.
 */
final class TestApplication extends Container
{
    public function runningUnitTests(): bool
    {
        return true;
    }

    public function runningInConsole(): bool
    {
        return true;
    }
}
