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
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'aggregate',
        'columns',
        'unions',
        'lock',
    ];

    /**
     * @inheritdoc
     */
    function compileInsert(Builder $query, array $values)
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $table = $this->wrapTable($query->from);

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        $columns = array_keys(reset($values));

        $parameters = [];

        foreach ($values as $record){

            $parameters[] =  array_combine($columns, $record);
        }
        $parameters = json_encode($parameters);
        $parameters = preg_replace('/"(\@B\w+)"/', '$1', $parameters);

        return "FOR doc IN ".$parameters." INSERT doc INTO ".$table;
    }

    function columnize(array $columns)
    {
        $resultColumns = [];
        foreach ($columns as $column){
            $wrapColumn = $this->wrap($column);
            $resultColumns[] =  $wrapColumn . ': ' . static::DOCUMENT_NAME . '.' . $wrapColumn;
        }
        return implode(',', $resultColumns);
    }

    function compileSelect(Builder $query)
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
        // function for the component which is responsible for making the SQL.
        $sql = trim($this->concatenate(
            $this->compileComponents($query))
        );
        $query->columns = $original;

        return $sql;
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
        // If the query is actually performing an aggregating select, we will let that
        // compiler handle the building of the select clauses, as it will need some
        // more syntax that is best handled by that function to keep things neat.
        if (! is_null($query->aggregate)) {
            return;
        }
        if(count($columns) === 1 && $columns[0] === "*"){
            return 'RETURN '.static::DOCUMENT_NAME;
        }


        return "RETURN { " . $this->columnize($columns) . " }";
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

    protected function wrapColumn($column){
        return 'doc.`'.$column.'`';
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