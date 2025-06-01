<?php

namespace Pannella\Cti;

use Illuminate\Database\Eloquent\Builder;

class SubtypeQueryBuilder extends Builder
{
    /**
     * Add a join to the subtype table if querying subtype columns
     */
    protected function addSubtypeJoinIfNeeded(string $column)
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
     * Override where to handle subtype columns
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Override whereIn to handle subtype columns
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    /**
     * Override orderBy to handle subtype columns
     */
    public function orderBy($column, $direction = 'asc')
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::orderBy($column, $direction);
    }

    /**
     * Override groupBy to handle subtype columns
     */
    public function groupBy(...$groups)
    {
        foreach ($groups as $group) {
            if (is_string($group)) {
                $this->addSubtypeJoinIfNeeded($group);
            }
        }

        return parent::groupBy(...$groups);
    }

    /**
     * Override having to handle subtype columns
     */
    public function having($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_string($column)) {
            $this->addSubtypeJoinIfNeeded($column);
        }

        return parent::having($column, $operator, $value, $boolean);
    }

    /**
     * Override select to handle subtype columns
     */
    public function select($columns = ['*'])
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
     * Override aggregate functions to handle subtype columns
     */
    public function aggregate($function, $columns = ['*'])
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