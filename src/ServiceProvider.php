<?php

namespace Flamix\Settings;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * This provider is deferred and should be lazy loaded.
     *
     * @var boolean
     */
    protected $defer = true;

    /**
     * Register IoC bindings.
     */
    public function register()
    {
        // Bind the manager as a singleton on the container.
        $this->app->singleton('Flamix\Settings\SettingsManager', fn($app) => new SettingsManager($app));

        // Provide a shortcut to the SettingStore for injecting into classes.
        $this->app->bind('Flamix\Settings\SettingStore', function ($app) {
            return $app->make('Flamix\Settings\SettingsManager')->driver();
        });

        $this->app->alias('Flamix\Settings\SettingStore', 'setting');
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'settings');
    }

    /**
     * Boot the package.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('settings.php')
        ], 'config');
        
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Which IoC bindings the provider provides.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'Flamix\Settings\SettingsManager',
            'Flamix\Settings\SettingStore',
            'setting'
        );
    }
}
