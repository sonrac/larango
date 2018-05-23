<?php

namespace sonrac\Arango\Query\Processors;

use ArangoDBClient\Document;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as IlluminateProcessor;

class Processor extends IlluminateProcessor
{
    /**
     * {@inheritdoc}
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $result = $query->getConnection()->insert($sql, $values);

        $document = $result->getAll()[0];
        /* @var Document $document */

        return $document->getKey();
    }
}
