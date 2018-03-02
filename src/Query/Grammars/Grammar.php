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
use sonrac\Arango\Query\QueryBuilder;

class Grammar extends IlluminateGrammar
{
    const DOCUMENT_NAME = 'doc';

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

    function compileInsertGetId(Builder $query, $values, $sequence)
    {
        return $this->compileInsert($query, $values) . ' RETURN NEW';
    }

    /**
     * @inheritdoc
     */
    function compileInsert(Builder $query, array $values)
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $collection = $this->wrapTable($query->from);

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

        $aql =  "FOR doc IN ".$parameters." INSERT doc INTO ".$collection;
        //var_dump($aql);
        return $aql;
    }

    /**
     * Wrap column and add table name
     * @param $column
     * @param bool $withCollection
     * @return string
     */
    public function wrapColumn($column, $withCollection = true){
        if($column !== '_key'){
            $column = '`'.$column.'`';
        }
        if($withCollection){
            $column = 'doc.'.$column;
        }
        return $column;
    }

    /**
     * Check table name valid
     *
     * @param  \Illuminate\Database\Query\Expression|string  $table
     * @return string
     */
    public function wrapTable($table)
    {
        //TODO: Check Is valid table?.
        return $table;
    }

    function columnize(array $columns)
    {
        $resultColumns = [];
        foreach ($columns as $column){
            $wrapColumn = '`'.$column.'`';
            $resultColumns[] =  $wrapColumn . ': ' . static::DOCUMENT_NAME . '.' . $wrapColumn;
        }
        return implode(',', $resultColumns);
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
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

        // If the query has any "join" clauses, we will setup the joins on the builder
        // and compile them so we can attach them to this update, as update queries
        // can get join statements to attach to other tables when they're needed.
        $joins = '';

        if (isset($query->joins)) {
            $joins = ' '.$this->compileJoins($query, $query->joins);
        }

        // Of course, update queries may also be constrained by where clauses so we'll
        // need to compile the where clauses and attach it to the query so only the
        // intended records are updated by the SQL statements we generate to run.
        $wheres = $this->compileWheres($query);

        $aql = "FOR doc IN ".$table.$joins." ".$wheres." UPDATE doc WITH { ".$columns." } IN ".$table;
        var_dump($aql);
        return $aql;
    }

    /**
     * Compile a delete statement into AQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        $wheres = is_array($query->wheres) ? $this->compileWheres($query) : '';

        $table = $this->wrapTable($query->from);
        $aql = "FOR doc in {$table} $wheres REMOVE doc IN {$table}";
        var_dump($aql);
        return $aql;
    }

    /**
     * Compile a select query into AQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
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

        if(!is_null($query->aggregate)){
            $aql = $this->compileAggregateExtended($query, $query->aggregate, $aql);
        }
        $query->columns = $original;

        return $aql;
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return array
     */
    protected function compileComponents(Builder $query)
    {
        $aql = [];

        foreach ($this->selectComponents as $component) {
            // To compile the query, we'll spin through each component of the query and
            // see if that component exists. If it does we'll just call the compiler
            // function for the component which is responsible for making the SQL.
            if (! is_null($query->$component)) {
                if($component === 'aggregate'){
                    continue;
                }
                $method = 'compile'.ucfirst($component);

                $aql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $aql;
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $collection
     * @return string
     */
    protected function compileFrom(Builder $query, $collection)
    {
        return 'FOR '.static::DOCUMENT_NAME.' IN '.$this->wrapCollection($collection);
    }

    /**
     * @inheritdoc
     */
    protected function compileColumns(Builder $query, $columns)
    {
        if(count($columns) === 1 && $columns[0] === "*"){
            return 'RETURN '.static::DOCUMENT_NAME;
        }


        return "RETURN { " . $this->columnize($columns) . " }";
    }


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
        $conjunction = $query instanceof JoinClause ? 'on' : 'FILTER';

        return $conjunction.' '.$this->removeLeadingBoolean(implode(' ', $sql));
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
        return $this->wrapColumn($where['column']).' '.$where['operator'].' '.$where['value'];
    }

    /**
     * @inheritdoc
     */
    protected function whereIn(Builder $query, $where)
    {
        if (! empty($where['values'])) {
            return '['.implode(",", $where['values']).'] ANY == '.$this->wrapColumn($where['column']);
        }

        return '0 = 1';
    }

    /**
     * @inheritdoc
     */
    protected function whereNotIn(Builder $query, $where)
    {
        if (! empty($where['values'])) {
            return '['.implode(",", $where['values']).'] NONE == '.$this->wrapColumn($where['column']);
        }

        return '0 = 1';
    }

    protected function whereNull(Builder $query, $where)
    {
        return $this->wrapColumn($where['column']).' == NULL';
    }

    protected function whereNotNull(Builder $query, $where)
    {
        return $this->wrapColumn($where['column']).' != NULL';
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


    protected function compileLimit(Builder $query, $limit)
    {
        $result = 'LIMIT ';

        if(isset($query->offset)){
            $result .= (int)$query->offset.', ';
        }
        $result .= (int)$limit;

        return $result;
    }

    protected function compileOffset(Builder $query, $offset)
    {
        if(!isset($query->limit)){
            throw new \Exception("You can't set offset without limit for arangodb");
        }
        return '';
    }

    /**
     * @param string $collection
     * @return string
     */
    protected function wrapCollection($collection){
        return "`".$collection."`";
    }
}