<?php

namespace Pannella\Cti;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Pannella\Cti\Exceptions\SubtypeException;
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
            throw SubtypeException::missingTable();
        }

        try {
            $keyName = $this->subtypeKeyName ?? $this->getKeyName();
            $key = $this->getKey();

            if (!$key) {
                throw SubtypeException::missingTypeId(static::class);
            }

            $data = array_intersect_key($this->getAttributes(), array_flip($this->subtypeAttributes));

            if ($this->getConnection()->table($this->subtypeTable)->where($keyName, $key)->exists()) {
                $updated = $this->getConnection()->table($this->subtypeTable)
                    ->where($keyName, $key)
                    ->update($data);
                
                if ($updated === false) {
                    throw SubtypeException::saveFailed($this->subtypeTable);
                }
            } else {
                $inserted = $this->getConnection()->table($this->subtypeTable)
                    ->insert(array_merge([$keyName => $key], $data));
                
                if (!$inserted) {
                    throw SubtypeException::saveFailed($this->subtypeTable);
                }
            }
        } catch (\Exception $e) {
            if ($e instanceof SubtypeException) {
                throw $e;
            }
            throw new SubtypeException("Failed to save subtype data: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete subtype row first, then parent row.
     */
    public function delete()
    {
        try {
            if ($this->fireModelEvent('subtypeDeleting') === false) {
                return false;
            }

            if ($this->exists && $this->subtypeTable) {
                $keyName = $this->subtypeKeyName ?? $this->getKeyName();
                if (!$this->getKey()) {
                    throw SubtypeException::missingTypeId(static::class);
                }

                $deleted = $this->getConnection()->table($this->subtypeTable)
                    ->where($keyName, $this->getKey())
                    ->delete();

                if ($deleted === false) {
                    throw new SubtypeException("Failed to delete subtype data from {$this->subtypeTable}");
                }

                $this->fireModelEvent('subtypeDeleted');
            }

            return parent::delete();
        } catch (\Exception $e) {
            if ($e instanceof SubtypeException) {
                throw $e;
            }
            throw new SubtypeException("Failed to delete subtype: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Load subtype data for this model from the subtype table.
     */
    public function loadSubtypeData()
    {
        if (!$this->subtypeTable) {
            throw SubtypeException::missingTable();
        }

        try {
            $keyName = $this->subtypeKeyName ?? $this->getKeyName();
            $key = $this->getKey();

            if (!$key) {
                throw SubtypeException::missingTypeId(static::class);
            }

            $data = $this->getConnection()->table($this->subtypeTable)
                ->where($keyName, $key)
                ->first();

            if ($data) {
                $this->forceFill((array) $data);
                $this->exists = true;
            }
        } catch (\Exception $e) {
            if ($e instanceof SubtypeException) {
                throw $e;
            }
            throw new SubtypeException("Failed to load subtype data: {$e->getMessage()}", 0, $e);
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
