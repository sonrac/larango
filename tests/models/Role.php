<?php

namespace sonrac\Arango\tests\models;

use sonrac\Arango\Eloquent\Model as Eloquent;

class Role extends Eloquent
{
    protected $table = 'roles';
    protected static $unguarded = true;

    public function user()
    {
        return $this->belongsTo('User');
    }
}
