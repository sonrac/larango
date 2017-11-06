<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango\tests;

use ArangoDBClient\ClientException;
use ArangoDBClient\ConnectionOptions;
use ArangoDBClient\UpdatePolicy;
use PHPUnit\Framework\TestCase;
use sonrac\Arango\Connection;

/**
 * Class ConnectionTestTest
 * Test ConnectionTestTest
 *
 * @author Donii Sergii <doniysa@gmail.com>
 */
class ConnectionOptionsTest extends TestCase
{
    /**
     * Test get database name
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function testGetDB()
    {
        $connection = new Connection();

        $this->assertEquals('_system', $connection->getDatabaseName());

        $connection = new Connection([
            ConnectionOptions::OPTION_DATABASE => '_test',
        ]);

        $this->assertEquals('_test', $connection->getDatabaseName());
    }

    /**
     * Test get endpoint
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function testGetEndpoint() {
        $connection = new Connection();

        $this->assertEquals('tcp://127.0.0.1:8529', $connection->getEndPoint());

        $connection = new Connection([
            ConnectionOptions::OPTION_ENDPOINT => 'tcp://127.0.0.1:85298',
        ]);

        $this->assertEquals('tcp://127.0.0.1:85298', $connection->getEndPoint());
    }

    /**
     * Test get auth type
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function testGetAuthType() {
        $connection = new Connection();

        $this->assertEquals('Basic', $connection->getAuthType());
    }

    /**
     * Test get auth user
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function testGetAuthUser() {
        $connection = new Connection();

        $this->assertEquals('root', $connection->getAuthUser());

        $connection = new Connection([
            ConnectionOptions::OPTION_AUTH_USER => 'user',
        ]);

        $this->assertEquals('user', $connection->getAuthUser());
    }

    /**
     * Test get auth password
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function testGetAuthPassword() {
        $connection = new Connection();

        $this->assertEquals('', $connection->getAuthPassword());

        $connection = new Connection([
            ConnectionOptions::OPTION_AUTH_PASSWD => 'user',
        ]);

        $this->assertEquals('user', $connection->getAuthPassword());
    }

    /**
     * Test reconnect connection
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function testGetConection() {
        $connection = new Connection();

        $this->assertEquals('Keep-Alive', $connection->getConnectionOption());

        $connection = new Connection([
            ConnectionOptions::OPTION_CONNECTION => 'Close',
        ]);

        $this->assertEquals('Close', $connection->getConnectionOption());
    }

    /**
     * Test reconnect connection
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function testGetTimeout() {
        $connection = new Connection();

        $this->assertEquals(3, $connection->getTimeout());

        $connection = new Connection([
            ConnectionOptions::OPTION_TIMEOUT => 5,
        ]);

        $this->assertEquals(5, $connection->getTimeout());
    }

    /**
     * Test reconnect connection
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function testReconnect() {
        $connection = new Connection();

        $this->assertTrue($connection->getReconnect());

        $connection = new Connection([
            ConnectionOptions::OPTION_RECONNECT => false,
        ]);

        $this->assertFalse($connection->getReconnect());
    }

    /**
     * Test reconnect connection
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function testCreateOption() {
        $connection = new Connection();

        $this->assertTrue($connection->getCreate());

        $connection = new Connection([
            ConnectionOptions::OPTION_CREATE => false,
        ]);

        $this->assertFalse($connection->getCreate());
    }

    /**
     * Test update policy option
     *
     * @author Donii Sergii <doniysa@gmail.com>
     */
    public function testUpdatePolicy() {
        $connection = new Connection();

        $this->assertEquals(UpdatePolicy::LAST, $connection->getUpdatePolicy());

        $connection = new Connection([
            ConnectionOptions::OPTION_UPDATE_POLICY => UpdatePolicy::ERROR,
        ]);

        $this->assertEquals(UpdatePolicy::ERROR, $connection->getUpdatePolicy());
    }

}
