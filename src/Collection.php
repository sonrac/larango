<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango;

use ArangoDBClient\Collection as ArangoCollection;

/**
 * Class Collection.
 *
 * @author  Donii Sergii <doniysa@gmail.com>
 */
class Collection
{
    /**
     * Arango.DB Connection
     *
     * @var \sonrac\Arango\Connection
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    protected $connection;

    /**
     * Arango.DB collection object
     *
     * @var \ArangoDBClient\Collection
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    protected $collection;

    /**
     * Collection constructor.
     *
     * @param \sonrac\Arango\Connection  $connection Arango.DB connection
     * @param \ArangoDBClient\Collection $collection Arango.DB collection object
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function __construct(Connection $connection, ArangoCollection $collection)
    {
        $this->connection = $connection;
        $this->collection = $collection;
    }
}
