<?php namespace sonrac\Arango\tests\models;

use sonrac\Arango\Eloquent\Model as Eloquent;

class Client extends Eloquent
{
    protected $table = 'clients';
    protected static $unguarded = true;

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function photo()
    {
        return $this->morphOne(Photo::class, 'imageable');
    }
}
