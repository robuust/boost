<?php

namespace Robuust\Boost;

use Illuminate\Support\ServiceProvider;
use Robuust\Boost\Boost\BoostConfiguration;

/**
 * Feeds Laravel Boost on Laravel and Craft 6 projects. Does nothing without `laravel/boost`.
 */
class BoostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!self::boostInstalled()) {
            return;
        }

        $this->mergeConfigFrom(\dirname(__DIR__).'/config/robuust-boost.php', 'robuust-boost');
    }

    public function boot(): void
    {
        if (!self::boostInstalled()) {
            return;
        }

        Stack::useRoot($this->app->basePath());

        $config = $this->app->make('config');
        $values = BoostConfiguration::for(Stack::current(), static fn (string $key): mixed => $config->get($key));

        foreach ($values as $key => $value) {
            $config->set($key, $value);
        }
    }

    public static function boostInstalled(): bool
    {
        return class_exists(\Laravel\Boost\BoostServiceProvider::class);
    }
}
