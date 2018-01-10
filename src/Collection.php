<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango;

use ArangoDBClient\Collection as ArangoCollection;
use ArrayAccess;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use IteratorAggregate;
use JsonSerializable;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Class Collection.
 *
 * @author  Donii Sergii <doniysa@gmail.com>
 */
class Collection extends BaseCollection implements ArrayAccess, Arrayable, Countable, IteratorAggregate, Jsonable, JsonSerializable
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
     * Items
     *
     * @var null|array
     *
     * @author Donii Sergii <doniysa@gmail.com>4
     */
    protected $items = null;

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
        $this->items = $this->collection->getAll();
    }

    /**
     * Get element from collection
     *
     * @param string $name
     *
     * @return mixed
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function __get($name)
    {
        if (isset($this->items[$name])) {
            return $this->items[$name];
        }

        return parent::__get($name);
    }

    /**
     * Add to collection
     *
     * @param string $name  Name
     * @param mixed  $value Value
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function __set($name, $value)
    {
        $this->items[$name] = $value;
    }
}
