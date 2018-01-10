<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango\Schema;

use \Illuminate\Database\Schema\Builder as SchemaBuilder;

/**
 * Class Schema
 *
 * @package sonrac\Arango\Schema
 *
 * @author  Donii Sergii <doniysa@gmail.com>
 */
class Builder extends SchemaBuilder
{
    /**
     * {@inheritdoc}
     */
    public function hasColumn($table, $column)
    {
        return true;
    }

    
}
