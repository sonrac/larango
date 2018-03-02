<?php
namespace sonrac\Arango\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use sonrac\Arango\Query\QueryBuilder;
use \Illuminate\Database\Eloquent\Builder as BaseBuilder;

abstract class Model extends BaseModel
{
    protected $primaryKey = '_key';

    protected $keyType = 'string';

    /**
     * Get the table qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName(){
        return $this->getKeyName();
    }

    function getCollectionName(){
        return $this->getTable();
    }

    /**
     * @param string $collection
     */
    function setCollectionName(string $collection){
        $this->table = $collection;
    }

    /**
     * @inheritdoc
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * @inheritdoc
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder(
            $connection, $connection->getQueryGrammar(), $connection->getPostProcessor()
        );
    }

    /**
     * @inheritdoc
     */
    protected function setKeysForSaveQuery(BaseBuilder $query)
    {
        $query->where($this->getKeyName(), '==', $this->getKeyForSaveQuery());

        return $query;
    }
}
