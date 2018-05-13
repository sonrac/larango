<?php

namespace sonrac\Arango\tests\models;

use sonrac\Arango\Eloquent\Model as Eloquent;

class Item extends Eloquent
{
    protected $table = 'items';
    protected static $unguarded = true;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSharp($query)
    {
        return $query->where('type', 'sharp');
    }
}
