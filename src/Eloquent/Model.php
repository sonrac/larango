<?php
namespace sonrac\Arango\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use sonrac\Arango\Query\QueryBuilder;
use \Illuminate\Database\Eloquent\Builder as BaseBuilder;

abstract class Model extends BaseModel
{
    /**
     * @inheritdoc
     */
    protected $primaryKey = '_key';

    /**
     * @inheritdoc
     */
    protected $keyType = 'string';

    /**
     * Get the key name without collection (collection not use in AQL)
     *
     * @return string
     */
    public function getQualifiedKeyName(){
        return $this->getKeyName();
    }

    /**
     * Get collection name
     * @return string
     */
    function getCollectionName(){
        return $this->getTable();
    }

    /**
     * Set collection name
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
