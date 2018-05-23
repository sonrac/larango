<?php

require __DIR__.'/../vendor/autoload.php';

/**
 * Created by PhpStorm.
 * User: admin
 * Date: 21.01.2018
 * Time: 14:25
 */

$connection = new \sonrac\Arango\Connection([

    \ArangoDBClient\ConnectionOptions::OPTION_ENDPOINT => 'tcp://arangodb:8529',
    \ArangoDBClient\ConnectionOptions::OPTION_DATABASE => 'test',
    \ArangoDBClient\ConnectionOptions::OPTION_AUTH_USER => 'root',
    \ArangoDBClient\ConnectionOptions::OPTION_AUTH_PASSWD => 'test',
]);

$processor = new \sonrac\Arango\Query\Processors\Processor();

$grammar = new \sonrac\Arango\Query\Grammars\Grammar();

$builder = new \sonrac\Arango\Query\QueryBuilder($connection, $grammar, $processor);

function selectWhere($builder)
{
    $builder = $builder->select()->from('documents')->where('data', 1);
    $aql = $builder->toAql();
    echo $aql . "\r\n";
    $data = $builder->get();
    var_dump($data);
}

function selectWhereNot($builder)
{
    $builder = $builder->select()->from('documents')->whereNot('data', 1);
    $aql = $builder->toAql();
    echo $aql . "\r\n";
    $data = $builder->get();
    var_dump($data);
}

function selectWhereAnd($builder)
{
    $builder = $builder->select()->from('documents')->where('data', 1)->whereNot('data', 2);
    $aql = $builder->toAql();
    echo $aql . "\r\n";
    $data = $builder->get();
    var_dump($data);
}

function selectWhereIn($builder)
{
    $builder = $builder->select()->from('documents')->whereIn('data', [1, 2]);
    $aql = $builder->toAql();
    echo $aql . "\r\n";
    $data = $builder->get();
    var_dump($data);
}

function selectWhereNotIn($builder)
{
    $builder = $builder->select()->from('documents')->whereNotIn('data', [1, 2]);
    $aql = $builder->toAql();
    echo $aql . "\r\n";
    $data = $builder->get();
    var_dump($data);
}

function selectWhereBetween($builder)
{
    $builder = $builder->select()->from('documents')->whereBetween('data', [0, 4]);
    $aql = $builder->toAql();
    echo $aql . "\r\n";
    $data = $builder->get();
    var_dump($data);
}

function selectWhereNotBetween($builder)
{
    $builder = $builder->select()->from('documents')->whereNotBetween('data', [5, 90]);
    $aql = $builder->toAql();
    echo $aql . "\r\n";
    $data = $builder->get();
    var_dump($data);
}

function selectWhereBetweenAnd($builder)
{
    $builder = $builder->select()->from('documents')->whereBetween('data', [0, 4])->where('data', 1);
    $aql = $builder->toAql();
    echo $aql . "\r\n";
    $data = $builder->get();
    var_dump($data);
}
function selectWhereBetweenOr($builder)
{
    $builder = $builder->select()->from('documents')->whereBetween('data', [0, 4])->orWhere('votes', 0);
    $aql = $builder->toAql();
    echo $aql . "\r\n";
    $data = $builder->get();
    var_dump($data);
}

$connection->table('users')->truncate();

$builder->where('test.a', '123');