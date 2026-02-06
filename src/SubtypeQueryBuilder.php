<?php

namespace Pannella\Cti;

use Illuminate\Database\Eloquent\Builder;

/**
 * Custom query builder for CTI models that handles subtype table joins.
 *
 * Extends Laravel's query builder to automatically join subtype tables
 * when querying subtype-specific columns. This allows seamless querying
 * across both parent and subtype tables.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends Builder<TModel>
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
     * @param \Closure|string|array<mixed> $column
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
        /** @phpstan-ignore-next-line function.alreadyNarrowedType */
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        /** @phpstan-ignore-next-line */
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
        /** @phpstan-ignore-next-line function.alreadyNarrowedType */
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        /** @phpstan-ignore-next-line */
        return parent::whereNotIn($column, $values, $boolean);
    }

    /**
     * Add a "where null" clause to the query, handling subtype columns.
     *
     * @param string|array<mixed> $columns
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereNull($columns, $boolean = 'and', $not = false): self
    {
        $cols = is_array($columns) ? $columns : [$columns];

        foreach ($cols as $column) {
            if (is_string($column)) {
                $this->addSubtypeJoinIfNeeded($column);
            }
        }

        /** @phpstan-ignore-next-line */
        return parent::whereNull($columns, $boolean, $not);
    }

    /**
     * Add a "where not null" clause to the query, handling subtype columns.
     *
     * @param string|array<mixed> $columns
     * @param string $boolean
     * @return $this
     */
    public function whereNotNull($columns, $boolean = 'and'): self
    {
        $cols = is_array($columns) ? $columns : [$columns];

        foreach ($cols as $column) {
            if (is_string($column)) {
                $this->addSubtypeJoinIfNeeded($column);
            }
        }

        /** @phpstan-ignore-next-line */
        return parent::whereNotNull($columns, $boolean);
    }

    /**
     * Add a "where column" clause to the query, handling subtype columns.
     *
     * @param \Closure|string|array<mixed> $first
     * @param string|null $operator
     * @param string|null $second
     * @param string|null $boolean
     * @return $this
     */
    public function whereColumn($first, $operator = null, $second = null, $boolean = 'and'): self
    {
        if (is_string($first)) {
            $this->addSubtypeJoinIfNeeded($first);
        }

        if (is_string($second)) {
            $this->addSubtypeJoinIfNeeded($second);
        }

        /** @phpstan-ignore-next-line */
        return parent::whereColumn($first, $operator, $second, $boolean);
    }

    /**
     * Add a "where between" clause to the query, handling subtype columns.
     *
     * @param \Illuminate\Database\Query\Expression<float|int|string>|string $column
     * @param iterable<mixed> $values
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false): self
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        /** @phpstan-ignore-next-line */
        return parent::whereBetween($column, $values, $boolean, $not);
    }

    /**
     * Add an "order by" clause to the query, handling subtype columns.
     *
     * @param \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Query\Expression<float|int|string>|string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc'): self
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        /** @phpstan-ignore-next-line */
        return parent::orderBy($column, $direction);
    }

    /**
     * Add a "group by" clause to the query, handling subtype columns.
     *
     * @param array<mixed>|string ...$groups
     * @return $this
     */
    public function groupBy(...$groups): self
    {
        foreach ($groups as $group) {
            if (is_string($group)) {
                $this->addSubtypeJoinIfNeeded($group);
            }
        }

        /** @phpstan-ignore-next-line */
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
        /** @phpstan-ignore-next-line function.alreadyNarrowedType */
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        /** @phpstan-ignore-next-line */
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

        /** @phpstan-ignore-next-line */
        return parent::select($columns);
    }

    /**
     * Execute an aggregate function on the database, handling subtype columns.
     *
     * @param string $function The aggregate function to execute (count, sum, avg, etc.)
     * @param array<int, string>|string $columns The columns to aggregate
     * @return mixed The result of the aggregate function
     */
    public function aggregate($function, $columns = ['*']): mixed
    {
        $columns = is_array($columns) ? $columns : [$columns];

        foreach ($columns as $column) {
            /** @phpstan-ignore-next-line function.alreadyNarrowedType */
            if (is_string($column)) {
                $this->addSubtypeJoinIfNeeded($column);
            }
        }

        return parent::aggregate($function, $columns);
    }
}