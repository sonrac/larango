<?php

namespace sonrac\Arango\tests\models;

use sonrac\Arango\Eloquent\Model as Eloquent;

class Photo extends Eloquent
{
    protected $table = 'photos';
    protected static $unguarded = true;

    public function imageable()
    {
        return $this->morphTo();
    }
}
