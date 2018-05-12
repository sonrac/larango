<?php
namespace sonrac\Arango\Helpers;


/**
 * Return entity name from prepared column or null if don't exist
 *
 * @param $column
 * @return null|string
 */
function getEntityNameFromColumn($column){
    $parts = explode('.', $column);
    if(count($parts) < 2){
        return null;
    }

    $tableOrEntityName = $parts[0];
    $postfix = '_entity';
    $postfixLength = strlen($postfix);
    if(strlen($tableOrEntityName) > $postfixLength && substr($tableOrEntityName, -$postfixLength) === $postfix){
        return $tableOrEntityName;
    }

    return getEntityName($tableOrEntityName);
}