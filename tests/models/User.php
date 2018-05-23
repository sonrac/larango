<?php

namespace sonrac\Arango\tests\models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use sonrac\Arango\Eloquent\Model as Eloquent;

class User extends Eloquent implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword;

    protected $dates = ['birthday', 'entry.date'];
    protected $fillable = ['username', 'email', 'birthday', 'msisdn', 'first_name', 'last_name', 'password', 'api_token', 'status', 'type', 'created_at', 'middle_name'];

    protected static $unguarded = true;

    public function books()
    {
        return $this->hasMany(Book::class, 'author_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function role()
    {
        return $this->hasOne(Role::class);
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, null, 'users', 'groups');
    }

    public function photos()
    {
        return $this->morphMany(Photo::class, 'imageable');
    }

    protected function getDateFormat()
    {
        return 'l jS \of F Y h:i:s A';
    }
}
