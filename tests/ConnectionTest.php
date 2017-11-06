<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango\tests;

use ArangoDBClient\CollectionHandler;
use ArangoDBClient\ConnectionOptions;
use PHPUnit\Framework\TestCase;
use sonrac\Arango\Connection;

/**
 * Class ConnectionTest
 * Connection test.
 *
 * @package sonrac\Arango\tests
 *
 * @author  Donii Sergii <doniysa@gmail.com>
 */
class ConnectionTest extends TestCase
{
    /**
     * Test create connection.
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function testCreateConnection()
    {
        $connection = new Connection();

        $this->assertInstanceOf(\ArangoDBClient\Connection::class, $connection->createConnection());

        $this->assertInstanceOf(\ArangoDBClient\Connection::class, $connection->createConnection([
            ConnectionOptions::OPTION_ENDPOINT => ''
        ]));

        $this->assertInstanceOf(\ArangoDBClient\Connection::class, $connection->getArangoClient());

        $this->assertInstanceOf(CollectionHandler::class, $connection->getArangoDB());
    }
}