<?php
namespace sonrac\Arango\tests\models;

use sonrac\Arango\Eloquent\Model;
use sonrac\Arango\Eloquent\SoftDeletes;

class Soft extends Model
{
    use SoftDeletes;

    protected $table = 'soft';
    protected static $unguarded = true;
    protected $dates = ['deleted_at'];
}
