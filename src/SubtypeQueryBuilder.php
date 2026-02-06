<?php

namespace Pannella\Cti;

use Illuminate\Database\Eloquent\Builder;

/**
 * Custom query builder for CTI models that handles subtype table joins.
 *
 * Extends Laravel's query builder to automatically join subtype tables
 * when querying subtype-specific columns. This allows seamless querying
 * across both parent and subtype tables.
 */
class SubtypeQueryBuilder extends Builder
{
    /**
     * Add a join to the subtype table if querying subtype columns.
     *
     * @param string $column The column being queried
     * @return void
     */
    protected function addSubtypeJoinIfNeeded($column)
    {
        $model = $this->getModel();

        if (!$model instanceof SubtypeModel) {
            return;
        }

        $subtypeTable = $model->getSubtypeTable();
        if (!$subtypeTable) {
            return;
        }

        // Handle table qualified columns
        $parts = explode('.', $column);
        $columnName = count($parts) > 1 ? end($parts) : $column;

        // Check if column belongs to subtype
        if (!in_array($columnName, $model->getSubtypeAttributes())) {
            return;
        }

        // Check if join already exists
        $joins = collect($this->getQuery()->joins);
        if ($joins->contains('table', $subtypeTable)) {
            return;
        }

        // Add join
        $this->join(
            $subtypeTable,
            $model->getTable() . '.' . $model->getKeyName(),
            '=',
            $subtypeTable . '.' . $model->getSubtypeKeyName()
        );
    }

    /**
     * Add basic where clause to the query, handling subtype columns.
     *
     * @param string|array|\Closure $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and'): self
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Add a "where in" clause to the query, handling subtype columns.
     *
     * @param string $column
     * @param mixed $values
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false): self
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    /**
     * Add a "where not in" clause to the query, handling subtype columns.
     *
     * @param string $column
     * @param mixed $values
     * @param string $boolean
     * @return $this
     */
    public function whereNotIn($column, $values, $boolean = 'and'): self
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::whereNotIn($column, $values, $boolean);
    }

    /**
     * Add a "where between" clause to the query, handling subtype columns.
     *
     * @param string|\Illuminate\Database\Query\Expression $column
     * @param iterable $values
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false): self
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::whereBetween($column, $values, $boolean, $not);
    }

    /**
     * Add an "order by" clause to the query, handling subtype columns.
     *
     * @param string|\Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Query\Expression $column
     * @param string $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc'): self
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::orderBy($column, $direction);
    }

    /**
     * Add a "group by" clause to the query, handling subtype columns.
     *
     * @param array|string ...$groups
     * @return $this
     */
    public function groupBy(...$groups): self
    {
        foreach ($groups as $group) {
            if (is_string($group)) {
                $this->addSubtypeJoinIfNeeded($group);
            }
        }

        return parent::groupBy(...$groups);
    }

    /**
     * Add a "having" clause to the query, handling subtype columns.
     *
     * @param string $column
     * @param string|null $operator
     * @param string|null $value
     * @param string $boolean
     * @return $this
     */
    public function having($column, $operator = null, $value = null, $boolean = 'and'): self
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::having($column, $operator, $value, $boolean);
    }

    /**
     * Add a basic select clause to the query, handling subtype columns.
     *
     * @param array|mixed $columns
     * @return $this
     */
    public function select($columns = ['*']): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        foreach ($columns as $column) {
            if (is_string($column)) {
                $this->addSubtypeJoinIfNeeded($column);
            }
        }

        return parent::select($columns);
    }

    /**
     * Execute an aggregate function on the database, handling subtype columns.
     *
     * @param string $function The aggregate function to execute (count, sum, avg, etc.)
     * @param array|string $columns The columns to aggregate
     * @return mixed The result of the aggregate function
     */
    public function aggregate($function, $columns = ['*']): mixed
    {
        $columns = is_array($columns) ? $columns : [$columns];

        foreach ($columns as $column) {
            if (is_string($column)) {
                $this->addSubtypeJoinIfNeeded($column);
            }
        }

        return parent::aggregate($function, $columns);
    }
}