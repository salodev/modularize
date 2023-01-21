<?php

namespace Salodev\Modularize\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    public function loadCommands(string $path)
    {
        $this->load($path);
    }
}
