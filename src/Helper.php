<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 09.05.2018
 * Time: 15:00
 */

namespace sonrac\Arango;


class Helper
{
    /**
     * Return entity name by collection name
     *
     * @param $collection
     * @return string
     */
    static function getEntityName($collection){
        return $collection.'_entity';
    }

    /**
     * Return entity name from prepared column or null if don't exist
     *
     * @param $column
     * @return null|string
     */
    static function getEntityNameFromColumn($column){
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

        return static::getEntityName($tableOrEntityName);
    }
}