<?php

namespace sonrac\Arango\Helpers;

/**
 * Return entity name by collection name
 *
 * @param $collection
 * @return string
 */
function getEntityName($collection)
{
    return $collection.'_entity';
}
