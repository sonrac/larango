<?php
namespace sonrac\Arango\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Support\Str;
use sonrac\Arango\Eloquent\Reletations\BelongsTo;
use sonrac\Arango\Eloquent\Reletations\BelongsToMany;
use function sonrac\Arango\Helpers\getEntityName;
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
     * Get collection name
     *
     * @return string
     */
    function getCollection(){
        return $this->getTable();
    }

    /**
     * Set collection name
     *
     * @param string $collection
     */
    function setCollection($collection){
        $this->table = $collection;
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
    public function qualifyColumn($column)
    {
        if (Str::contains($column, '.')) {
            return $column;
        }

        return $this->getEntityName().'.'.$column;
    }

    public function getEntityName(){
        return getEntityName($this->getCollection());
    }

    /**
     * @inheritdoc
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $model = $this->newInstance([], true);

        $attributesResult = [];
        foreach ($attributes as $key => $value){
            if(is_array($value) && $this->getEntityName() === $key){

                $attributesResult = array_merge($attributesResult, $value);
                continue;
            }
            $attributesResult[$key] = $value;
        }

        $model->setRawAttributes((array) $attributesResult, true);

        $model->setConnection($connection ?: $this->getConnectionName());

        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * @inheritdoc
     */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * @inheritdoc
     */
    function newBelongsTo(BaseBuilder $query, BaseModel $child, $foreignKey, $ownerKey, $relation)
    {
        return new BelongsTo($query, $child, $foreignKey, $ownerKey, $relation);
    }

    /**
     * @inheritdoc
     */
    function newBelongsToMany(BaseBuilder $query, BaseModel $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = null)
    {
        return new BelongsToMany($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
    }
}
