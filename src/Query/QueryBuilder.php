<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango\Query;

use ArangoDBClient\Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as IlluminateBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use sonrac\Arango\Connection;
use sonrac\Arango\Query\Grammars\Grammar;
use function sonrac\Arango\Helpers\getEntityName;
use function sonrac\Arango\Helpers\getEntityNameFromColumn;

/**
 * Class QueryBuilder.
 *
 * @author  Donii Sergii <doniysa@gmail.com>
 */
class QueryBuilder extends IlluminateBuilder
{
    /**
     * @var Grammar
     */
    public $grammar;

    public $bindings = [];

    public $operators = [
        '==',     //equality
        '!=',     //inequality
        '<',      //less than
        '<=',     //less or equal
        '>',      //greater than
        '>=',     //greater or equal
        'IN',     //test if a value is contained in an array
        'NOT IN', //test if a value is not contained in an array
        'LIKE',   //tests if a string value matches a pattern
        '=~',     //tests if a string value matches a regular expression
        '!~',     //tests if a string value does not match a regular expression
    ];

    /**
     * {@inheritdoc}
     */
    public function pluck($column, $key = null)
    {
        $column = $this->prepareColumn($column);
        if (!is_null($key)) {
            $key = $this->prepareColumn($key);
        }
        $results = $this->get(is_null($key) ? [$column] : [$column, $key]);

        // If the columns are qualified with a table or have an alias, we cannot use
        // those directly in the "pluck" operations since the results from the DB
        // are only keyed by the column itself. We'll strip the table out here.
        return $results->pluck(
            $this->stripTableForPluck($column),
            $this->stripTableForPluck($key)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function addSelect($column)
    {
        $column = is_array($column) ? $column : func_get_args();

        $column = collect($column)->map(function ($column) {
            return $this->prepareColumn($column);
        })->toArray();

        $this->columns = array_merge((array) $this->columns, $column);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $join = new JoinClause($this, $type, $table);

        // If the first "column" of the join is really a Closure instance the developer
        // is trying to build a join with a complex "on" clause containing more than
        // one condition, so we'll add the join and call a Closure with the query.
        if ($first instanceof \Closure) {
            call_user_func($first, $join);

            $this->joins[] = $join;

            $this->addBinding($join->getBindings(), 'join');
        }

        // If the column is simply a string, we can assume the join simply has a basic
        // "on" clause with a single condition. So we will just build the join with
        // this simple join clauses attached to it. There is not a join callback.
        else {
            $method = $where ? 'where' : 'on';

            $this->joins[] = $join->$method($first, $operator, $second);

            $this->addBinding($join->getBindings(), 'join');
        }

        //Move wheres from join to main query (arangoDB don't have "on" method)
        foreach ($join->wheres as $where) {
            $this->wheres[] = $where;
        }

        $join->wheres = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function orderBy($column, $direction = 'asc')
    {
        $column = $this->prepareColumn($column);
        $this->{$this->unions ? 'unionOrders' : 'orders'}[] = [
            'column' => $column,
            'direction' => strtolower($direction) == 'asc' ? 'asc' : 'desc',
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotIn' : 'In';

        if ($values instanceof Builder) {
            $values = $values->getQuery();
        }

        // If the value is a query builder instance we will assume the developer wants to
        // look for any values that exists within this given query. So we will add the
        // query accordingly so that this query is properly executed when it is run.
        if ($values instanceof self) {
            return $this->whereInExistingQuery(
                $column, $values, $boolean, $not
            );
        }

        // If the value of the where in clause is actually a Closure, we will assume that
        // the developer is using a full sub-select for this "in" statement, and will
        // execute those Closures, then we can re-construct the entire sub-selects.
        if ($values instanceof \Closure) {
            return $this->whereInSub($column, $values, $boolean, $not);
        }

        // Next, if the value is Arrayable we need to cast it to its raw array form so we
        // have the underlying array value instead of an Arrayable object which is not
        // able to be added as a binding, etc. We will then add to the wheres array.
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        // Finally we'll add a binding for each values unless that value is an expression
        // in which case we will just skip over it since it will be the query as a raw
        // string and not as a parameterized place-holder to be replaced by the PDO.
        foreach ($values as $index => $value) {
            if (!$value instanceof Expression) {
                $this->addBinding($value, 'where');
                $values[$index] = $this->getLastBindingKey();
            }
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        return $this;
    }

    /**
     * You can get last binding key from getLastBindingKey
     * {@inheritdoc}
     */
    public function addBinding($value, $type = 'where')
    {
        if (is_array($value)) {
            foreach ($value as $variable) {
                $this->bindings[$this->getBindingVariableName()] = $variable;
            }
        } else {
            $this->bindings[$this->getBindingVariableName()] = $value;
        }

        return $this;
    }

    /**
     * Return last binding key
     *
     * @return string
     */
    public function getLastBindingKey()
    {
        $keys = array_keys($this->getBindings());
        return '@' . array_pop($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * {@inheritdoc}
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $this->where(function (QueryBuilder $query) use ($column, $values, $boolean, $not) {
            list($from, $to) = $values;
            if (!$not) {
                $query->where($column, '>', $from);
                $query->where($column, '<', $to);
            } else {
                $query->where($column, '<=', $from);
                $query->orWhere($column, '>=', $to);
            }
        }, $boolean);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $column = $this->prepareColumn($column);

        //For compatibility with internal framework functions
        if ($operator === '=') {
            $operator = '==';
        }
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        list($value, $operator) = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() == 2
        );

        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parenthesis.
        // We'll add that Closure to the query then return back out immediately.
        if ($column instanceof \Closure) {
            return $this->whereNested($column, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            list($value, $operator) = [$operator, '=='];
        }

        // If the value is a Closure, it means the developer is performing an entire
        // sub-select within the query and we will need to compile the sub-select
        // within the where clause to get the appropriate query record results.
        if ($value instanceof \Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        // If the value is "null", we will just assume the developer wants to add a
        // where null clause to the query. So, we will allow a short-cut here to
        // that method for convenience so the developer doesn't have to check.
        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator !== '==');
        }

        // If the column is making a JSON reference we'll check to see if the value
        // is a boolean. If it is, we'll add the raw boolean string as an actual
        // value to the query to ensure this is properly handled by the query.
        if (Str::contains($column, '->') && is_bool($value)) {
            $value = new Expression($value ? 'true' : 'false');
        }

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.
        $type = 'Basic';

        if (!$value instanceof Expression) {
            $this->addBinding($value, 'where');
            $value = $this->getLastBindingKey();
        }

        $this->wheres[] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function whereColumn($first, $operator = null, $second = null, $boolean = 'and')
    {
        if ($operator === '=') {
            $operator = '==';
        }

        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($first)) {
            return $this->addArrayOfWheres($first, $boolean, 'whereColumn');
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            list($second, $operator) = [$operator, '=='];
        }

        // Finally, we will add this where clause into this array of clauses that we
        // are building for the query. All of them will be compiled via a grammar
        // once the query is about to be executed and run against the database.
        $type = 'Column';

        $this->wheres[] = compact(
            'type', 'first', 'operator', 'second', 'boolean'
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function whereNull($column, $boolean = 'and', $not = false)
    {
        $column = $this->prepareColumn($column);

        $type = $not ? 'NotNull' : 'Null';

        $this->wheres[] = compact('type', 'column', 'boolean');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException('Non-numeric value passed to increment method.');
        }

        $wrapped = $this->prepareColumn($column);

        $columns = array_merge([$column => $this->raw("$wrapped + $amount")], $extra);

        return $this->update($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException('Non-numeric value passed to decrement method.');
        }

        $wrapped = $this->prepareColumn($column);

        $columns = array_merge([$column => $this->raw("$wrapped - $amount")], $extra);

        return $this->update($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $values)
    {
        foreach ($values as $index => $value) {
            if (!$value instanceof Expression) {
                $this->addBinding($value, 'update');
                $values[$index] = $this->getLastBindingKey();
            }
        }

        $aql = $this->grammar->compileUpdate($this, $values);

        return $this->connection->update($aql, $this->getBindings());
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (!is_null($id)) {
            $this->where($this->from.'.id', '=', $id);
        }

        return $this->connection->delete(
            $this->grammar->compileDelete($this), $this->getBindings()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function truncate()
    {
        $connection = $this->getConnection();
        /**
         * @var Connection
         */
        $arangoDB = $connection->getArangoDB();
        $arangoDB->truncate($this->from);
    }

    /**
     * {@inheritdoc}
     */
    public function find($id, $columns = ['*'])
    {
        $column = $this->prepareColumn('_key');
        return $this->where($column, '==', $id)->limit(1)->first($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function insertGetId(array $values, $sequence = null)
    {
        if (!is_array(reset($values))) {
            $values = [$values];
        }

        foreach ($values as $i => $record) {
            foreach ($record as $j => $value) {
                $this->addBinding($value, 'insert');
                $values[$i][$j] = $this->getLastBindingKey();
            }
        }

        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);

        return $this->processor->processInsertGetId($this, $sql, $this->getBindings(), $sequence);
    }

    /**
     * {@inheritdoc}
     */
    public function sum($columns = '*')
    {
        return (int) $this->aggregate(strtoupper(__FUNCTION__), Arr::wrap($columns));
    }

    /**
     * {@inheritdoc}
     */
    public function count($columns = '*')
    {
        return (int) $this->aggregate(strtoupper(__FUNCTION__), Arr::wrap($columns));
    }

    /**
     * {@inheritdoc}
     */
    public function aggregate($function, $columns = ['*'])
    {
        $results = $this->cloneWithout(['columns'])
            ->cloneWithoutBindings(['select'])
            ->setAggregate($function, $columns)
            ->get($columns);

        if (!$results->isEmpty()) {
            return array_change_key_case((array) $results[0])['aggregate'];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function cloneWithoutBindings(array $except)
    {
        return tap(clone $this, function ($clone) use ($except) {
            foreach ($except as $type) {
                unset($clone->bindings[$type]);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function insert(array $values)
    {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        // Here, we will sort the insert keys for every record so that each insert is
        // in the same order for the record. We need to make sure this is the case
        // so there are not any errors or problems when inserting these records.
        else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        $values = $this->prepareColumns($values);

        foreach ($values as $i => $record) {
            foreach ($record as $j => $value) {
                $this->addBinding($value, 'insert');
                $values[$i][$j] = $this->getLastBindingKey();
            }
        }

        $aql = $this->grammar->compileInsert($this, $values);

        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one huge, flattened array for execution.
        return $this->connection->insert(
            $aql,
            $this->getBindings()
        );
    }

    /**
     * Compile select to Aql format
     * @return string
     */
    public function toAql()
    {
        $aql = $this->grammar->compileSelect($this);
        return $aql;
    }

    /**
     * {@inheritdoc}
     */
    protected function addDynamic($segment, $connector, $parameters, $index)
    {
        // Once we have parsed out the columns and formatted the boolean operators we
        // are ready to add it to this query as a where clause just like any other
        // clause on the query. Then we'll increment the parameter index values.
        $bool = strtolower($connector);

        $this->where(Str::snake($segment), '==', $parameters[$index], $bool);
    }

    /**
     * {@inheritdoc}
     */
    protected function runSelect()
    {
        return $this->connection->select(
            $this->toAql(), $this->getBindings()
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function setAggregate($function, $columns)
    {
        $this->aggregate = compact('function', 'columns');

        if (empty($this->groups)) {
            $this->orders = null;

            unset($this->bindings['order']);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function invalidOperatorAndValue($operator, $value)
    {
        return is_null($value) && in_array($operator, $this->operators) &&
            !in_array($operator, ['==', '!=']);
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareValueAndOperator($value, $operator, $useDefault = false)
    {
        if ($useDefault) {
            return [$operator, '=='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new \InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    /**
     * Check exist entity name in joins or it base entity. Throw exception if didn't find.
     * @param $column
     * @throws Exception
     */
    protected function checkColumnIfJoin($column)
    {
        if (empty($this->joins)) {
            return;
        }
        $columnEntityName = getEntityNameFromColumn($column);

        if (is_null($columnEntityName)) {
            throw new Exception('You can\'t use column '.$column.' without entity name, with join.');
        }

        if ($columnEntityName === getEntityName($this->from)) {
            return;
        }

        foreach ($this->joins as $join) {
            $joinEntityName = getEntityName($join->table);
            if ($columnEntityName === $joinEntityName) {
                return;
            }
        }
        throw new Exception('You can\'t use column '.$column.' with this joins.');
    }

    /**
     * Prepate columns from values array
     * @param $values
     * @return array
     * @throws \Exception
     */
    protected function prepareColumns($values)
    {
        $res = [];
        foreach ($values as $key => $value) {
            $column = $this->prepareColumn($key);
            $res[$column] = $value;
        }
        return $res;
    }

    /**
     * Check column for joins and wrap column (add table name and wrap in ``)
     *
     * @param $column
     * @return string
     * @throws Exception
     */
    protected function prepareColumn($column)
    {
        $this->checkColumnIfJoin($column);

        $column = $this->grammar->wrapColumn($column, $this->from);

        return $column;
    }

    /**
     * Get next binding variable name
     *
     * @return string
     */
    protected function getBindingVariableName()
    {
        return 'B' . (count($this->bindings) + 1);
    }

    /**
     * Return table from prepared column or null
     *
     * @param string $column
     * @return null|string
     */
    protected function stripTableForPluck($column)
    {
        if (is_null($column)) {
            return null;
        }
        $column = explode('.', $column)[1];

        return trim($column, '`');
    }
}
