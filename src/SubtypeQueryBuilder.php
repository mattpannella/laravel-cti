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
     * Map of forwarded method names (lowercased) to parameter indices containing column names.
     *
     * @var array<string, array<int, int>>
     */
    protected static array $columnMethodMap = [
        'orwherein' => [0], 'orwherenotin' => [0],
        'orwherenull' => [0], 'orwherenotnull' => [0],
        'orwherebetween' => [0], 'wherenotbetween' => [0], 'orwherenotbetween' => [0],
        'wheredate' => [0], 'orwheredate' => [0],
        'wheretime' => [0], 'orwheretime' => [0],
        'whereday' => [0], 'orwhereday' => [0],
        'wheremonth' => [0], 'orwheremonth' => [0],
        'whereyear' => [0], 'orwhereyear' => [0],
        'orderbydesc' => [0],
        'wherejsoncontains' => [0], 'orwherejsoncontains' => [0],
        'wherejsonlength' => [0], 'orwherejsonlength' => [0],
        'wherejsondoesntcontain' => [0], 'orwherejsondoesntcontain' => [0],
        'orwherecolumn' => [0, 2],
        'addselect' => [0],
    ];

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

        // Add left join to avoid dropping parent records missing subtype data
        $this->leftJoin(
            $subtypeTable,
            $model->getTable() . '.' . $model->getKeyName(),
            '=',
            $subtypeTable . '.' . $model->getSubtypeKeyName()
        );
    }

    /**
     * Intercept forwarded method calls to add subtype joins for column-bearing methods.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $key = strtolower($method);
        if (isset(static::$columnMethodMap[$key])) {
            foreach (static::$columnMethodMap[$key] as $index) {
                if (isset($parameters[$index])) {
                    $col = $parameters[$index];
                    if (is_array($col)) {
                        foreach ($col as $c) {
                            if (is_string($c)) {
                                $this->addSubtypeJoinIfNeeded($c);
                            }
                        }
                    } elseif (is_string($col)) {
                        $this->addSubtypeJoinIfNeeded($col);
                    }
                }
            }
        }

        return parent::__call($method, $parameters);
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
     * @param \Illuminate\Database\Query\Expression<literal-string>|string $column
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
     * @param \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Query\Expression<literal-string>|string $column
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

    /**
     * Add an "order by" clause for a descending sort, handling subtype columns.
     *
     * @param mixed $column
     * @return $this
     */
    public function latest($column = null): self
    {
        if (is_null($column)) {
            $column = $this->getModel()->getCreatedAtColumn() ?? 'created_at';
        }

        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::latest($column);
    }

    /**
     * Add an "order by" clause for an ascending sort, handling subtype columns.
     *
     * @param mixed $column
     * @return $this
     */
    public function oldest($column = null): self
    {
        if (is_null($column)) {
            $column = $this->getModel()->getCreatedAtColumn() ?? 'created_at';
        }

        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::oldest($column);
    }

    /**
     * Get an array with the values of a given column, handling subtype columns.
     *
     * @param string|mixed $column
     * @param string|null $key
     * @return \Illuminate\Support\Collection<array-key, mixed>
     */
    public function pluck($column, $key = null)
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }
        if (is_string($key)) {
            $this->addSubtypeJoinIfNeeded($key);
        }

        return parent::pluck($column, $key);
    }

    /**
     * Get a single column's value from the first result, handling subtype columns.
     *
     * @param string|mixed $column
     * @return mixed
     */
    public function value($column)
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::value($column);
    }

    /**
     * Update records in the database, splitting values between parent and subtype tables.
     *
     * @param array<string, mixed> $values
     * @return int
     */
    public function update(array $values)
    {
        $model = $this->getModel();

        if (!$model instanceof SubtypeModel || !$model->getSubtypeTable()) {
            return parent::update($values);
        }

        $subtypeAttrs = array_flip($model->getSubtypeAttributes());
        $subtypeValues = array_intersect_key($values, $subtypeAttrs);
        $parentValues = array_diff_key($values, $subtypeAttrs);

        $parentAffected = 0;
        $subtypeAffected = 0;

        if (!empty($parentValues)) {
            $parentAffected = parent::update($parentValues);
        }

        if (!empty($subtypeValues)) {
            $subtypeTable = $model->getSubtypeTable();
            $keyName = $model->getKeyName();
            $subtypeKeyName = $model->getSubtypeKeyName();

            // Add join so the where clauses referencing subtype columns work for ID plucking
            foreach ($subtypeValues as $col => $val) {
                $this->addSubtypeJoinIfNeeded($col);
            }

            $ids = $this->pluck($model->getTable() . '.' . $keyName)->all();

            if (!empty($ids)) {
                $subtypeAffected = $model->getConnection()->table($subtypeTable)
                    ->whereIn($subtypeKeyName, $ids)
                    ->update($subtypeValues);
            }
        }

        return max($parentAffected, $subtypeAffected);
    }

    /**
     * Increment a column's value by a given amount, handling subtype columns.
     *
     * @param string|mixed $column
     * @param float|int $amount
     * @param array<string, mixed> $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        $model = $this->getModel();

        if (!$model instanceof SubtypeModel || !$model->getSubtypeTable()) {
            return parent::increment($column, $amount, $extra);
        }

        $subtypeAttrs = $model->getSubtypeAttributes();

        if (!is_string($column) || !in_array($column, $subtypeAttrs)) {
            return parent::increment($column, $amount, $extra);
        }

        $this->addSubtypeJoinIfNeeded($column);

        $subtypeTable = $model->getSubtypeTable();
        $keyName = $model->getKeyName();
        $subtypeKeyName = $model->getSubtypeKeyName();

        $ids = $this->pluck($model->getTable() . '.' . $keyName)->all();

        if (empty($ids)) {
            return 0;
        }

        // Split extra values between parent and subtype
        $subtypeAttrFlip = array_flip($subtypeAttrs);
        $subtypeExtra = array_intersect_key($extra, $subtypeAttrFlip);
        $parentExtra = array_diff_key($extra, $subtypeAttrFlip);

        $parentAffected = 0;
        if (!empty($parentExtra)) {
            $parentAffected = parent::update($parentExtra);
        }

        $subtypeAffected = $model->getConnection()->table($subtypeTable)
            ->whereIn($subtypeKeyName, $ids)
            ->increment($column, $amount, $subtypeExtra);

        return max($parentAffected, $subtypeAffected);
    }

    /**
     * Decrement a column's value by a given amount, handling subtype columns.
     *
     * @param string|mixed $column
     * @param float|int $amount
     * @param array<string, mixed> $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        $model = $this->getModel();

        if (!$model instanceof SubtypeModel || !$model->getSubtypeTable()) {
            return parent::decrement($column, $amount, $extra);
        }

        $subtypeAttrs = $model->getSubtypeAttributes();

        if (!is_string($column) || !in_array($column, $subtypeAttrs)) {
            return parent::decrement($column, $amount, $extra);
        }

        $this->addSubtypeJoinIfNeeded($column);

        $subtypeTable = $model->getSubtypeTable();
        $keyName = $model->getKeyName();
        $subtypeKeyName = $model->getSubtypeKeyName();

        $ids = $this->pluck($model->getTable() . '.' . $keyName)->all();

        if (empty($ids)) {
            return 0;
        }

        // Split extra values between parent and subtype
        $subtypeAttrFlip = array_flip($subtypeAttrs);
        $subtypeExtra = array_intersect_key($extra, $subtypeAttrFlip);
        $parentExtra = array_diff_key($extra, $subtypeAttrFlip);

        $parentAffected = 0;
        if (!empty($parentExtra)) {
            $parentAffected = parent::update($parentExtra);
        }

        $subtypeAffected = $model->getConnection()->table($subtypeTable)
            ->whereIn($subtypeKeyName, $ids)
            ->decrement($column, $amount, $subtypeExtra);

        return max($parentAffected, $subtypeAffected);
    }
}
