<?php

namespace sonrac\Arango\tests;

/**
 * Class BaseTestCase
 *
 * @author Donii Sergii <doniysa@gmail.com>
 */
abstract class BaseTestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            \sonrac\Arango\ArangoServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application    $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $config = require 'config/database.php';

        $app['config']->set('app.key', 'ZsZewWyUJ5FsKp9lMwv4tYbNlegQilM7');

        $app['config']->set('database.default', 'arangodb');
        $app['config']->set('database.connections.arangodb', $config['connections']['arangodb']);

        $app['config']->set('auth.model', 'User');
        $app['config']->set('auth.providers.users.model', 'User');
        $app['config']->set('cache.driver', 'array');
    }
}