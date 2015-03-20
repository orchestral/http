<?php namespace Orchestra\Http;

use Hashids\Hashids;
use Orchestra\Support\Providers\ServiceProvider;

class HashIdServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('orchestra.hashid', function ($app) {
            return new Hashids($app['config']['app.key']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['orchestra.hashid'];
    }
}
