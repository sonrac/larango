<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango\tests;

use ArangoDBClient\ConnectionOptions;
use PHPUnit\Framework\TestCase;
use sonrac\Arango\Connection;

/**
 * Class ConnectionTestTest
 * Test ConnectionTestTest
 *
 * @author Donii Sergii <doniysa@gmail.com>
 */
class ConnectionTest extends TestCase
{
    /**
     * Test get database name
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function testGetDB() {
        $connection = new Connection();

        $this->assertEquals('_system', $connection->getDb());

        $connection = new Connection([
            ConnectionOptions::OPTION_DATABASE => '_test'
        ]);

        $this->assertEquals('_test', $connection->getDb());
    }
}
