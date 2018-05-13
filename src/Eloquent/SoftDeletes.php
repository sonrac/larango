<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 02.03.2018
 * Time: 16:43
 */

namespace sonrac\Arango\Eloquent;

use \Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;

trait SoftDeletes
{
    use BaseSoftDeletes;

    /**
     * Return column without collection name (collection not use in AQL query)
     * @return string
     */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->getDeletedAtColumn();
    }
}