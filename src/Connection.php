<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango;

use ArangoDBClient\CollectionHandler as ArangoDBCollectionHandler;
use ArangoDBClient\Connection as ArangoDBConnection;
use ArangoDBClient\ConnectionOptions as ArangoDBConnectionOptions;
use ArangoDBClient\Exception as ArangoException;
use ArangoDBClient\UpdatePolicy as ArangoDBUpdatePolicy;
use Illuminate\Database\Connection as IlluminateConnection;

/**
 * Class Connection.
 * Arango connection.
 *
 * @author  Donii Sergii <doniysa@gmail.com>
 */
class Connection extends IlluminateConnection
{
    /**
     * Arango.DB Query Builder
     *
     * @var
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    protected $db;

    protected $database = '_system';

    /**
     * Arango.DB connection
     *
     * @var \ArangoDBClient\Connection
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
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

        $this->arangoConnection = $this->createConnection();

        $this->db = new ArangoDBCollectionHandler($this->arangoConnection);
    }

    /**
     * Get Arango.DB Query Builder
     *
     * @return mixed
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getArangoDB()
    {
        return $this->db;
    }

    /**
     * Get Arango.DB client
     *
     * @return \ArangoDBClient\Connection
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getArangoClient()
    {
        return $this->arangoConnection;
    }

    /**
     * Create new arango.db connection.
     *
     * @param array $config Config
     *
     * @return \ArangoDBClient\Connection
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function createConnection(array $config = [])
    {
        $config = $this->config + $config;

        ArangoException::enableLogging();

        return new ArangoDBConnection($this->getMainConnectionOption() + $config);
    }

    /**
     * Get database name.
     *
     * @return string
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getDatabaseName()
    {
        $this->database = $this->getOption(ArangoDBConnectionOptions::OPTION_DATABASE, $this->database);

        return parent::getDatabaseName();
    }

    /**
     * Get arango.db endpoint.
     *
     * @return string
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getEndPoint()
    {
        return $this->getOption(ArangoDBConnectionOptions::OPTION_ENDPOINT, 'tcp://127.0.0.1:8529');
    }

    /**
     * Get auth type
     *
     * @return string
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getAuthType()
    {
        return $this->getOption(ArangoDBConnectionOptions::OPTION_AUTH_TYPE, 'Basic');
    }

    /**
     * Get auth type
     *
     * @return string
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getAuthUser()
    {
        return $this->getOption(ArangoDBConnectionOptions::OPTION_AUTH_USER, 'root');
    }

    /**
     * Get auth type
     *
     * @return string
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getAuthPassword()
    {
        return $this->getOption(ArangoDBConnectionOptions::OPTION_AUTH_PASSWD, '');
    }

    /**
     * Get connection option
     *
     * @return string
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getConnectionOption()
    {
        return $this->getOption(ArangoDBConnectionOptions::OPTION_CONNECTION, 'Keep-Alive');
    }

    /**
     * Get timeout option
     *
     * @return int
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getTimeout()
    {
        return $this->getOption(ArangoDBConnectionOptions::OPTION_TIMEOUT, 3);
    }

    /**
     * Get reconnect option
     *
     * @return bool
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getReconnect()
    {
        return $this->getOption(ArangoDBConnectionOptions::OPTION_RECONNECT, true);
    }

    /**
     * Get create option
     *
     * @return mixed
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getCreate()
    {
        return $this->getOption(ArangoDBConnectionOptions::OPTION_CREATE, true);
    }

    /**
     * Get update policy option
     *
     * @return string
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function getUpdatePolicy()
    {
        return $this->getOption(ArangoDBConnectionOptions::OPTION_UPDATE_POLICY, ArangoDBUpdatePolicy::LAST);
    }

    /**
     * Get option value
     *
     * @param string $optionName
     * @param mixed  $default
     *
     * @return mixed
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    private function getOption($optionName, $default)
    {
        if (!isset($this->config[$optionName])) {
            $this->config[$optionName] = $default;
        }

        return $this->config[$optionName];
    }

    /**
     * Get main connection option
     *
     * @return array
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    private function getMainConnectionOption()
    {
        return [
            // database name
            ArangoDBConnectionOptions::OPTION_DATABASE      => $this->getDatabaseName(),
            // server endpoint to connect to
            ArangoDBConnectionOptions::OPTION_ENDPOINT      => $this->getEndPoint(),
            // authorization type to use (currently supported: 'Basic')
            ArangoDBConnectionOptions::OPTION_AUTH_TYPE     => $this->getAuthType(),
            // user for basic authorization
            ArangoDBConnectionOptions::OPTION_AUTH_USER     => $this->getAuthUser(),
            // password for basic authorization
            ArangoDBConnectionOptions::OPTION_AUTH_PASSWD   => $this->getAuthPassword(),
            // connection persistence on server. can use either 'Close' (one-time connections) or 'Keep-Alive' (re-used connections)
            ArangoDBConnectionOptions::OPTION_CONNECTION    => $this->getConnectionOption(),
            // connect timeout in seconds
            ArangoDBConnectionOptions::OPTION_TIMEOUT       => $this->getTimeout(),
            // whether or not to reconnect when a keep-alive connection has timed out on server
            ArangoDBConnectionOptions::OPTION_RECONNECT     => $this->getReconnect(),
            // optionally create new collections when inserting documents
            ArangoDBConnectionOptions::OPTION_CREATE        => $this->getCreate(),
            // optionally create new collections when inserting documents
            ArangoDBConnectionOptions::OPTION_UPDATE_POLICY => $this->getUpdatePolicy(),
        ];
    }
}
