<?php

namespace Pannella\Cti;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Pannella\Cti\Traits\HasSubtypeRelations;

abstract class SubtypeModel extends Model
{
    use HasSubtypeRelations;

    // Name of the subtype table (e.g. assessment_quiz)
    protected $subtypeTable;

    // Attributes that belong to the subtype table
    protected $subtypeAttributes = [];

    // Optionally override if subtype PK column name differs from parent PK
    protected $subtypeKeyName;

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'subtypeSaving' => null,
        'subtypeSaved' => null,
        'subtypeDeleting' => null,
        'subtypeDeleted' => null,
    ];

    /**
     * Save parent model and subtype data inside a transaction.
     */
    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            if ($this->fireModelEvent('subtypeSaving') === false) {
                return false;
            }

            $saved = parent::save($options);

            if ($saved) {
                $this->saveSubtypeData();
                $this->fireModelEvent('subtypeSaved');
            }

            return $saved;
        });
    }

    /**
     * Save subtype table data, insert or update as needed.
     */
    protected function saveSubtypeData()
    {
        if (!$this->subtypeTable) {
            throw new \RuntimeException("Subtype table must be defined.");
        }

        $keyName = $this->subtypeKeyName ?? $this->getKeyName();
        $key = $this->getKey();

        $data = array_intersect_key($this->getAttributes(), array_flip($this->subtypeAttributes));

        if ($this->getConnection()->table($this->subtypeTable)->where($keyName, $key)->exists()) {
            $this->getConnection()->table($this->subtypeTable)->where($keyName, $key)->update($data);
        } else {
            $this->getConnection()->table($this->subtypeTable)->insert(array_merge([$keyName => $key], $data));
        }
    }

    /**
     * Delete subtype row first, then parent row.
     */
    public function delete()
    {
        if ($this->fireModelEvent('subtypeDeleting') === false) {
            return false;
        }

        if ($this->exists && $this->subtypeTable) {
            $keyName = $this->subtypeKeyName ?? $this->getKeyName();
            $this->getConnection()->table($this->subtypeTable)->where($keyName, $this->getKey())->delete();
            $this->fireModelEvent('subtypeDeleted');
        }

        return parent::delete();
    }

    /**
     * Load subtype data for this model from the subtype table.
     */
    public function loadSubtypeData()
    {
        if (!$this->subtypeTable) {
            return;
        }
        $keyName = $this->subtypeKeyName ?? $this->getKeyName();
        $key = $this->getKey();

        $data = $this->getConnection()->table($this->subtypeTable)->where($keyName, $key)->first();

        if ($data) {
            $this->forceFill((array) $data);
            $this->exists = true;
        }
    }

    /**
     * Override forceFill to ensure subtype attributes are set.
     */
    public function forceFill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->subtypeAttributes)) {
                $this->setAttribute($key, $value);
            }
        }

        return parent::forceFill($attributes);
    }

    /**
     * Create a new Eloquent query builder for the model.
     */
    public function newEloquentBuilder($query)
    {
        return new SubtypeQueryBuilder($query);
    }

    /**
     * Get the subtype attributes
     */
    public function getSubtypeAttributes(): array
    {
        return $this->subtypeAttributes;
    }

    public function getSubtypeTable(): string
    {
        return $this->subtypeTable;
    }

    public function getSubtypeKeyName(): string 
    {
        return $this->subtypeKeyName ?? $this->getKeyName();
    }

    /**
     * Fire a model event with the given name.
     */
    protected function fireModelEvent($event, $halt = true)
    {
        if (! isset($this->dispatchesEvents[$event])) {
            return parent::fireModelEvent($event, $halt);
        }

        $method = $halt ? 'until' : 'dispatch';

        return static::$dispatcher->{$method}(new $this->dispatchesEvents[$event]($this));
    }
}
