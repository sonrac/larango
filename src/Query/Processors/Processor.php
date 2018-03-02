<?php
namespace sonrac\Arango\Query\Processors;
use ArangoDBClient\Cursor;
use ArangoDBClient\Document;
use Illuminate\Database\Query\Builder;
use \Illuminate\Database\Query\Processors\Processor as IlluminateProcessor;
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 21.01.2018
 * Time: 15:19
 */

class Processor extends IlluminateProcessor
{
    function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $result = $query->getConnection()->insert($sql, $values);

        $document = $result->getAll()[0];
        /**
         * @var Document $document
         */

        return $document->getKey();
    }
}