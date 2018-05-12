<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 03.03.2018
 * Time: 15:37
 */

namespace sonrac\Arango\Eloquent\Reletations;

use \Illuminate\Database\Eloquent\Relations\BelongsTo as BaseBelongsTo;

class BelongsTo extends BaseBelongsTo
{

    /**
     * @inheritdoc
     */
    public function addEagerConstraints(array $models)
    {
        $this->query->whereIn($this->ownerKey, $this->getEagerModelKeys($models));
    }

    /**
     * @inheritdoc
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->ownerKey, '==', $this->child->{$this->foreignKey});
            $this->query->whereNotNull($this->ownerKey);
        }
    }
}