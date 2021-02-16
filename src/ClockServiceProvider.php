<?php


namespace Happy\Clock;

use Happy\Clock\Commands\ClockPushRed;
use Happy\Clock\Commands\ClockRedInit;
use Happy\Clock\Commands\MigrationCommand;
use Happy\Clock\Service\ClockService;
use Illuminate\Support\ServiceProvider;

class ClockServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        // Publish config files
        $this->publishes([
            __DIR__.'/config/config.php' => app()->basePath() . '/config/clock.php',
        ]);

        // Register commands
        $this->commands('command.clock.migration');
        $this->commands('command.clock.redInit');

        // Register blade directives
//        $this->bladeDirectives();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerEntrust();

        $this->registerCommands();

        $this->mergeConfig();
    }

    /**
     * Register the application bindings.
     *
     * @return void
     */
    private function registerEntrust()
    {
//        $this->app->bind('entrust', function ($app) {
//            return new Entrust($app);
//        });
//
//        $this->app->alias('entrust', 'Zizaco\Entrust\Entrust');
    }

    /**
     * Register the artisan commands.
     *
     * @return void
     */
    private function registerCommands()
    {
        $this->app->singleton('command.clock.migration', function ($app) {
            return new MigrationCommand();
        });
        $this->app->singleton('command.clock.redInit', function ($app) {
            return new ClockRedInit();
        });
        $this->app->singleton('command.clock.redPush', function ($app) {
            return new ClockPushRed();
        });
    }

    /**
     * Merges user's and entrust's configs.
     *
     * @return void
     */
    private function mergeConfig()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/config.php', 'clock'
        );
    }

    /**
     * Get the services provided.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'command.clock.migration',
            'command.clock.redInit',
            'command.clock.redPush'
        ];
    }
}
