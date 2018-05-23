<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango;

use ArangoDBClient\CollectionHandler as ArangoDBCollectionHandler;
use ArangoDBClient\Connection as ArangoDBConnection;
use ArangoDBClient\ConnectionOptions as ArangoDBConnectionOptions;
use ArangoDBClient\Document;
use ArangoDBClient\Exception as ArangoException;
use ArangoDBClient\Statement;
use ArangoDBClient\UpdatePolicy as ArangoDBUpdatePolicy;
use Illuminate\Database\Connection as IlluminateConnection;
use sonrac\Arango\Query\Grammars\Grammar;
use sonrac\Arango\Query\Processors\Processor;
use sonrac\Arango\Query\QueryBuilder;

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
     * @throws ArangoException
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        $this->arangoConnection = $this->createConnection();

        $this->db = new ArangoDBCollectionHandler($this->arangoConnection);

        // We need to initialize a query grammar and the query post processors
        // which are both very important parts of the database abstractions
        // so we initialize these to their default values while starting.
        $this->useDefaultQueryGrammar();

        $this->useDefaultPostProcessor();
    }

    /**
     * Send AQL request to ArangoDB and return response with flat array
     *
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return mixed
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            $query = $this->prepareBindingsInQuery($query);
            $options = [
                'query' => $query,
                'count' => true,
                'batchSize' => 1000,
                'sanitize'  => true,
            ];

            if (count($bindings) > 0) {
                $options['bindVars'] = $this->prepareBindings($bindings);
                var_dump($options['bindVars']);
            }

            $statement = new Statement($this->getArangoClient(), $options);

            $cursor = $statement->execute();

            $resultingDocuments = [];

            foreach ($cursor as $key => $document) {
                /* @var Document $document */
                $resultingDocuments[$key] = $document->getAll();
            }

            return $resultingDocuments;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        return new QueryBuilder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $query = $this->prepareBindingsInQuery($query);
            $options = [
                'query' => $query,
                'count' => true,
                'batchSize' => 1000,
                'sanitize'  => true,
            ];

            if (count($bindings) > 0) {
                $options['bindVars'] = $this->prepareBindings($bindings);
            }

            $statement = new Statement($this->getArangoClient(), $options);

            return $statement->execute();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $query = $this->prepareBindingsInQuery($query);

            $options = [
                'query' => $query,
                'count' => true,
                'batchSize' => 1000,
                'sanitize'  => true,
            ];

            if (count($bindings) > 0) {
                $options['bindVars'] = $this->prepareBindings($bindings);
            }

            $statement = new Statement($this->getArangoClient(), $options);

            return $statement->execute();
        });
    }

    /**
     * Get Arango.DB
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
     * @return ArangoDBConnection
     *
     * @throws \ArangoDBClient\Exception
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

    public function getDefaultQueryGrammar()
    {
        return new Grammar();
    }

    public function getDefaultPostProcessor()
    {
        return new Processor();
    }

    protected function prepareBindingsInQuery($query)
    {
        $query = explode('?', $query);
        $result = '';
        foreach ($query as $index => $part) {
            if ($index === count($query) - 1) {
                $result .= $part;
                continue;
            }
            $result .= $part.'@B'.($index + 1);
        }
        return $result;
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     *
     * @throws \ArangoDBClient\Exception
     * @return void
     */
    protected function reconnectIfMissingConnection()
    {
        if (is_null($this->arangoConnection)) {
            $this->arangoConnection = $this->createConnection();
        }
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
