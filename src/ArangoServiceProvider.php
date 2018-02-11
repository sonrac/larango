<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Connection as IlluminateConnection;

/**
 * Class ArangoServiceProvider.
 *
 * @author  Donii Sergii <doniysa@gmail.com>
 */
class ArangoServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register()
    {
        // Add database driver.
        $this->app->singleton('arangodb.connection', function($app){
            $config = config('database.connections.arangodb');
            return new Connection($config);
        });

        IlluminateConnection::resolverFor('arangodb', function ($config) {
            return app('arangodb.connection');
        });

        $this->app->resolving('db', function ($db) {
            $db->extend('arangodb', function ($config) {
                return app('arangodb.connection');
            });
        });
    }
}
