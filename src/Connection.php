<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango;

use ArangoDBClient\ConnectionOptions as ArangoDBConnectionOptions;
use Illuminate\Database\Connection as IlluminateConnection;

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
    public function getDb()
    {
        if (!isset($this->config[ArangoDBConnectionOptions::OPTION_DATABASE])) {
            $this->config[ArangoDBConnectionOptions::OPTION_DATABASE] = '_system';
        }

        return $this->config[ArangoDBConnectionOptions::OPTION_DATABASE];
    }
}
