<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango;

use Illuminate\Database\Connection as IlluminateConnection;
use ArangoDBClient\ConnectionOptions as ArangoDBConnectionOptions;
/**
 * Class Connection.
 * Arango connection.
 *
 * @author  Donii Sergii <doniysa@gmail.com>
 */
class Connection extends IlluminateConnection
{
    protected $db;

    protected $arangoConnection;

    /**
     * Connection constructor.
     *
     * @param array $config Connection options
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get database name.
     *
     * @return string
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getDb() {
        if (!isset($this->config[ArangoDBConnectionOptions::OPTION_DATABASE])) {
            $this->config[ArangoDBConnectionOptions::OPTION_DATABASE] = '_system';
        }

        return $this->config[ArangoDBConnectionOptions::OPTION_DATABASE];
    }
}