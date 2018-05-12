<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 21.01.2018
 * Time: 14:35
 */

namespace sonrac\Arango\Query\Grammars;

use Illuminate\Database\Query\Builder;
use \Illuminate\Database\Query\Grammars\Grammar as IlluminateGrammar;
use function sonrac\Arango\Helpers\getEntityName;
use function sonrac\Arango\Helpers\getEntityNameFromColumn;

class Grammar extends IlluminateGrammar
{
    /**
     * The components that make up a select clause.
     *
     * @var array
     */
    protected $selectComponents = [
        'aggregate',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'columns',
        'unions',
        'lock',
    ];

    /**
     * @inheritdoc
     */
    public function compileInsertGetId(Builder $query, $values, $sequence)
    {
        return $this->compileInsert($query, $values) . ' RETURN NEW';
    }

    /**
     * @inheritdoc
     */
    public function compileInsert(Builder $query, array $values)
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $collection = $this->wrapTable($query->from);

        $entityName = getEntityName($query->from);

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        $columns = array_keys(reset($values));

        $parameters = [];

        foreach ($values as $record){
            $bindValuesTmp = [];
            foreach ($columns as $column){
                if(!isset($record[$column])) continue;
                $bindValuesTmp[$column] = $record[$column];
            }
            $parameters[] =  $bindValuesTmp;
        }
        $parameters = json_encode($parameters);
        $parameters = preg_replace('/"(\@B\w+)"/', '$1', $parameters);

        $aql =  "FOR ".$entityName." IN ".$parameters." INSERT ".$entityName." INTO ".$collection;
        //var_dump($aql);
        return $aql;
    }

    /**
     * Prepare column before use in AQL request
     * Add entityName and wrap it if needed.
     * Add alias for string like (column as other_name)
     *
     * @param $collection
     * @param $column
     * @param bool $withCollection
     * @return string
     */
    public function wrapColumn($column, $collection = null, $withCollection = true){

        $entityName = getEntityNameFromColumn($column);

        $clearColumn = $this->getClearColumnName($column);

        $alias = $this->getAliasNameFromColumn($column);

        if(is_null($entityName) && !is_null($collection)){
            $entityName =  getEntityName($collection);
        }

        if($clearColumn !== '_key'){
            $clearColumn = trim($clearColumn, '`');
            $clearColumn = '`'.$clearColumn.'`';
        }
        if($withCollection){
            $column = $entityName.'.'.$clearColumn;
        }
        if($alias){
            $column = $alias.':'.$column;
        }
        return $column;
    }

    /**
     * Return collection name
     * @return string
     */
    public function wrapTable($table)
    {
        return $this->wrapCollection($table);
    }

    /**
     * @inheritdoc
     */
    function columnize(array $columns)
    {
        $resultColumns = [];
        foreach ($columns as $column){
            if(strpos($column, ':') !== false){
                $resultColumns[] = $column;
                continue;
            }

            list($entityName, $column) = explode(".", $column);
            if($column === '`*`'){
                $resultColumns[] = $entityName . ': '.$entityName;
                continue;
            }
            $resultColumns[] =  $column . ': ' . $entityName.'.'.$column;
        }
        return implode(',', $resultColumns);
    }

    /**
     * @inheritdoc
     */
    public function compileUpdate(Builder $query, $values)
    {
        $table = $this->wrapTable($query->from);

        // Each one of the columns in the update statements needs to be wrapped in the
        // keyword identifiers, also a place-holder needs to be created for each of
        // the values in the list of bindings so we can make the sets statements.
        $columns = collect($values)->map(function ($value, $key) {
            return $key.' : '.$value;
        })->implode(', ');

        $joins = '';

        if (isset($query->joins)) {
            $joins = $this->compileJoins($query, $query->joins).' ';
        }

        // Of course, update queries may also be constrained by where clauses so we'll
        // need to compile the where clauses and attach it to the query so only the
        // intended records are updated by the SQL statements we generate to run.
        $wheres = $this->compileWheres($query);

        $entityName = getEntityName($table);

        $aql = $joins."FOR ".$entityName." IN ".$table." ".$wheres.
            " UPDATE ".$entityName." WITH { ".$columns." } IN ".$table;

        var_dump($aql);
        return $aql;
    }

    /**
     * @inheritdoc
     */
    protected function compileJoins(Builder $query, $joins)
    {

        return collect($joins)->map(function ($join) use(&$aql) {
            $table = $this->wrapTable($join->table);
            $entityName = getEntityName($join->table);
            return 'FOR '.$entityName.' IN '.$table;
        })->implode(' ');
    }

    /**
     * @inheritdoc
     */
    public function compileDelete(Builder $query)
    {
        $wheres = is_array($query->wheres) ? $this->compileWheres($query) : '';

        $collection = $this->wrapTable($query->from);
        $entityName = getEntityName($collection);
        $aql = "FOR {$entityName} in {$collection} {$wheres} REMOVE {$entityName} IN {$collection}";
        var_dump($aql);
        return $aql;
    }

    /**
     * @inheritdoc
     */
    public function compileSelect(Builder $query)
    {
        // If the query does not have any columns set, we'll set the columns to the
        // * character to just get all of the columns from the database. Then we
        // can build the query and concatenate all the pieces together as one.
        $original = $query->columns;

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        // To compile the query, we'll spin through each component of the query and
        // see if that component exists. If it does we'll just call the compiler
        // function for the component which is responsible for making the AQL.
        $aql = trim($this->concatenate(
            $this->compileComponents($query))
        );

        if (isset($query->joins)) {
            $aql = $this->compileJoins($query, $query->joins).' '.$aql;
        }




        if(!is_null($query->aggregate)){
            $aql = $this->compileAggregateExtended($query, $query->aggregate, $aql);
        }
        $query->columns = $original;

        return $aql;
    }

    /**
     * @inheritdoc
     */
    protected function compileComponents(Builder $query)
    {
        $aql = [];

        foreach ($this->selectComponents as $component) {
            // To compile the query, we'll spin through each component of the query and
            // see if that component exists. If it does we'll just call the compiler
            // function for the component which is responsible for making the SQL.
            if (! is_null($query->$component)) {
                if($component === 'aggregate' ||
                   $component === 'joins'){
                    continue;
                }
                $method = 'compile'.ucfirst($component);

                $aql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $aql;
    }

    /**
     * @inheritdoc
     */
    protected function compileFrom(Builder $query, $collection)
    {
        return 'FOR '.getEntityName($collection).' IN '.$this->wrapCollection($collection);
    }

    /**
     * @inheritdoc
     */
    protected function compileColumns(Builder $query, $columns)
    {
        if(count($columns) === 1 && $columns[0] === "*"){
            return 'RETURN '.getEntityName($query->from);
        }


        return "RETURN { " . $this->columnize($columns) . " }";
    }

    /**
     * Return string for aggregate some column from AQL request
     *
     * @param Builder $query
     * @param $aggregate
     * @param $aql
     * @return string
     */
    protected function compileAggregateExtended(Builder $query, $aggregate, $aql)
    {
        return "RETURN {\"aggregate\":".$aggregate['function']."(".$aql.")}";
    }

    /**
     * @inheritdoc
     */
    protected function compileWheres(Builder $query)
    {
        // Each type of where clauses has its own compiler function which is responsible
        // for actually creating the where clauses SQL. This helps keep the code nice
        // and maintainable since each clause has a very small method that it uses.
        if (is_null($query->wheres)) {
            return '';
        }

        // If we actually have some where clauses, we will strip off the first boolean
        // operator, which is added by the query builders for convenience so we can
        // avoid checking for the first clauses in each of the compilers methods.
        if (count($sql = $this->compileWheresToArray($query)) > 0) {
            return $this->concatenateWhereClauses($query, $sql);
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    protected function concatenateWhereClauses($query, $sql)
    {
        return 'FILTER '.$this->removeLeadingBoolean(implode(' ', $sql));
    }

    /**
     * @inheritdoc
     */
    protected function compileWheresToArray($query)
    {
        return collect($query->wheres)->map(function ($where) use ($query) {
            return $where['boolean'].' '.$this->{"where{$where['type']}"}($query, $where);
        })->all();
    }

    /**
     * @inheritdoc
     */
    protected function whereBasic(Builder $query, $where)
    {
        return $where['column'].' '.$where['operator'].' '.$where['value'];
    }

    /**
     * @inheritdoc
     */
    protected function whereIn(Builder $query, $where)
    {
        if (! empty($where['values'])) {
            $column = $this->wrapColumn($where['column'], $query->from);
            return '['.implode(",", $where['values']).'] ANY == '.$column;
        }

        return '0 = 1';
    }

    /**
     * @inheritdoc
     */
    protected function whereNotIn(Builder $query, $where)
    {
        if (! empty($where['values'])) {
            $column = $this->wrapColumn($where['table'],$where['column']);
            return '['.implode(",", $where['values']).'] NONE == '.$column;
        }

        return '0 = 1';
    }

    /**
     * @inheritdoc
     */
    protected function whereNull(Builder $query, $where)
    {
        return $this->wrapColumn($where['column'], $query->from).' == NULL';
    }

    /**
     * @inheritdoc
     */
    protected function whereNotNull(Builder $query, $where)
    {
        return $this->wrapColumn($where['column'], $query->from).' != NULL';
    }

    /**
     * @inheritdoc
     */
    protected function whereColumn(Builder $query, $where)
    {
        $firstWrapColumn = $this->wrapColumn($where['first'], $query->from);
        $secondWrapColumn = $this->wrapColumn($where['second'], $query->from);
        return $firstWrapColumn.' '.$where['operator'].' '.$secondWrapColumn;
    }

    /**
     * @inheritdoc
     */
    protected function compileOrders(Builder $query, $orders)
    {
        if (! empty($orders)) {
            return 'SORT '.implode(', ', $this->compileOrdersToArray($query, $orders));
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    protected function compileOrdersToArray(Builder $query, $orders)
    {
        return array_map(function ($order) {
            return ! isset($order['sql'])
                ? $order['column'].' '.$order['direction']
                : $order['sql'];
        }, $orders);
    }

    /**
     * @inheritdoc
     */
    protected function compileLimit(Builder $query, $limit)
    {
        $result = 'LIMIT ';

        if(isset($query->offset)){
            $result .= (int)$query->offset.', ';
        }
        $result .= (int)$limit;

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function compileOffset(Builder $query, $offset)
    {
        if(!isset($query->limit)){
            throw new \Exception("You can't set offset without limit for arangodb");
        }
        return '';
    }

    /**
     * Wrap collection name
     * @param string $collection
     * @return string
     */
    protected function wrapCollection($collection){
        return "`".trim($collection,'`')."`";
    }

    protected function getClearColumnName($column){
        $parts = explode('.', $column);
        if(count($parts) > 1){
            $column = $parts[1];
        }
        $column = explode('as', $column)[0];

        return trim($column, '` ');
    }

    protected function getAliasNameFromColumn($column){
        $parts = explode('as', $column);
        if(count($parts) < 2){
            return null;
        }

        return '`'.trim($parts[1], '` ').'`';
    }
}