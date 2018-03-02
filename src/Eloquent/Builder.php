<?php
namespace sonrac\Arango\Eloquent;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Created by PhpStorm.
 * User: admin
 * Date: 21.01.2018
 * Time: 14:42
 */

class Builder extends \Illuminate\Database\Eloquent\Builder
{
    /**
     * @inheritdoc
     */
    public function whereKey($id)
    {
        if (is_array($id) || $id instanceof Arrayable) {
            $this->query->whereIn($this->model->getQualifiedKeyName(), $id);

            return $this;
        }

        return $this->where($this->model->getQualifiedKeyName(), '==', $id);
    }
}