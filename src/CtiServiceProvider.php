<?php

namespace Pannella\Cti;

use Illuminate\Support\ServiceProvider;

class CtiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cti.php', 'cti');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/cti.php' => $this->app->configPath('cti.php'),
        ], 'cti-config');
    }
}
