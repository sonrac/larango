<?php
/**
 * @author Donii Sergii <doniysa@gmail.com>
 */

namespace sonrac\Arango\Query;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as IlluminateBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use sonrac\Arango\Connection;

/**
 * Class QueryBuilder.
 *
 * @author  Donii Sergii <doniysa@gmail.com>
 */
class QueryBuilder extends IlluminateBuilder
{
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

    protected function getBindingVariableName()
    {
        return "B" . (count($this->bindings) + 1);
    }

    public function getLastBindingKey()
    {
        $keys = array_keys($this->getBindings());
        return "@" . array_pop($keys);
    }

    /**
     * Get the current query value bindings in a flattened array.
     *
     * @return array
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * @inheritdoc
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

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
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
            return $this->whereNull($column, $boolean, $operator !== '=');
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
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        if (! is_numeric($amount)) {
            throw new \InvalidArgumentException('Non-numeric value passed to increment method.');
        }

        $wrapped = $this->grammar->wrapColumn($column);

        $columns = array_merge([$column => $this->raw("$wrapped + $amount")], $extra);

        return $this->update($columns);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        if (! is_numeric($amount)) {
            throw new \InvalidArgumentException('Non-numeric value passed to decrement method.');
        }

        $wrapped = $this->grammar->wrapColumn($column);

        $columns = array_merge([$column => $this->raw("$wrapped - $amount")], $extra);

        return $this->update($columns);
    }

    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int
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

    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (! is_null($id)) {
            $this->where($this->from.'.id', '=', $id);
        }

        return $this->connection->delete(
            $this->grammar->compileDelete($this), $this->getBindings()
        );
    }

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
     * @inheritdoc
     */
    public function truncate()
    {
        $connection = $this->getConnection();
        /**
         * @var Connection $connection
         */
        $arangoDB = $connection->getArangoDB();
        $arangoDB->truncate($this->from);
    }

    /**
     * Execute a query for a single record by KEY.
     *
     * @param  string $id
     * @param  array $columns
     * @return mixed|static
     */
    public function find($id, $columns = ['*'])
    {
        return $this->where('doc._key', '==', $id)->first($columns);
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array   $values
     * @param  string|null  $sequence
     * @return int
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
     * @inheritdoc
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

    function sum($columns = '*')
    {
        return (int) $this->aggregate(strtoupper(__FUNCTION__), Arr::wrap($columns));
    }

    function count($columns = '*')
    {
        return (int) $this->aggregate(strtoupper(__FUNCTION__), Arr::wrap($columns));
    }

    public function aggregate($function, $columns = ['*'])
    {
        $results = $this->cloneWithout(['columns'])
            ->cloneWithoutBindings(['select'])
            ->setAggregate($function, $columns)
            ->get($columns);

        if (! $results->isEmpty()) {
            return array_change_key_case((array) $results[0])['aggregate'];
        }
    }

    /**
     * @inheritdoc
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
     * Insert a new record into the database.
     *
     * @param  array $values
     * @return bool
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

        foreach ($values as $i => $record) {
            foreach ($record as $j => $value) {
                $this->addBinding($value, 'insert');
                $values[$i][$j] = $this->getLastBindingKey();
            }
        }


        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one huge, flattened array for execution.
        return $this->connection->insert(
            $this->grammar->compileInsert($this, $values),
            $this->getBindings()
        );
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    protected function runSelect()
    {
        return $this->connection->select(
            $this->toAql(), $this->getBindings()
        );
    }

    public function toAql()
    {
        $aql = $this->grammar->compileSelect($this);
        var_dump($aql);
        return $aql;
    }
}
