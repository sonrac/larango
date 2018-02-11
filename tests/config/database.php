<?php

return [
    'connections' => [
        'arangodb' => [
            'name'       => 'arangodb',
            'driver'     => 'arangodb',
            \ArangoDBClient\ConnectionOptions::OPTION_ENDPOINT => 'tcp://arangodb:8529',
            \ArangoDBClient\ConnectionOptions::OPTION_DATABASE => 'test',
            \ArangoDBClient\ConnectionOptions::OPTION_AUTH_USER => 'root',
            \ArangoDBClient\ConnectionOptions::OPTION_AUTH_PASSWD => 'test'
        ]
    ]
];