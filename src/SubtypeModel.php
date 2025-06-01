<?php

namespace Pannella\Cti;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Pannella\Cti\Exceptions\SubtypeException;
use Pannella\Cti\Traits\HasSubtypeRelations;

/**
 * Base class for implementing Class Table Inheritance in Laravel models.
 * 
 * This abstract class extends Laravel's Model to support storing model data
 * across multiple tables in a class table inheritance pattern. The base/parent
 * class data is stored in one table while subtype-specific data is stored in
 * separate tables.
 *
 * @property string $subtypeTable Name of the table containing subtype-specific data
 * @property array $subtypeAttributes List of attributes that belong to the subtype table
 * @property string|null $subtypeKeyName Foreign key column name in subtype table
 *
 * @method bool save(array $options = []) Save both parent and subtype data
 * @method bool delete() Delete both parent and subtype data
 */
abstract class SubtypeModel extends Model
{
    use HasSubtypeRelations;

    public const EVENT_SUBTYPE_SAVING = 'subtypeSaving';
    public const EVENT_SUBTYPE_SAVED = 'subtypeSaved';
    public const EVENT_SUBTYPE_DELETING = 'subtypeDeleting';
    public const EVENT_SUBTYPE_DELETED = 'subtypeDeleted';

    // Name of the subtype table (e.g. assessment_quiz)
    protected ?string $subtypeTable = null;

    // Attributes that belong to the subtype table
    protected array $subtypeAttributes = [];

    // Optionally override if subtype PK column name differs from parent PK
    protected ?string $subtypeKeyName = null;

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
     * Save both parent and subtype data inside a database transaction.
     *
     * @param array $options Save options passed to parent save method
     * @return bool Whether the save was successful
     * @throws SubtypeException When saving subtype data fails
     */
    public function save(array $options = []): bool
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
     * Save subtype-specific data to the subtype table.
     * Will perform an insert or update depending on if the record exists.
     *
     * @throws SubtypeException When subtype table is not defined or save fails
     * @return void
     */
    protected function saveSubtypeData(): void
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
     * Delete both subtype and parent data.
     * Deletes subtype data first, then parent data to maintain referential integrity.
     *
     * @throws SubtypeException When deletion fails
     * @return bool Whether the deletion was successful
     */
    public function delete(): bool
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
     * Load subtype-specific data from the subtype table.
     *
     * @throws SubtypeException When loading fails or required data is missing
     * @return void
     */
    public function loadSubtypeData(): void
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
     * Force fill attributes, ensuring subtype attributes are properly set.
     *
     * @param array $attributes Attributes to fill
     * @return $this
     */
    public function forceFill(array $attributes): self
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
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return \Pannella\Cti\SubtypeQueryBuilder
     */
    public function newEloquentBuilder(Builder $query): SubtypeQueryBuilder
    {
        return new SubtypeQueryBuilder($query);
    }

    /**
     * Get the list of attributes that belong to the subtype table.
     *
     * @return array
     */
    public function getSubtypeAttributes(): array
    {
        return $this->subtypeAttributes;
    }

    /**
     * Get the name of the subtype table.
     *
     * @return ?string
     */
    public function getSubtypeTable(): ?string
    {
        return $this->subtypeTable;
    }

    /**
     * Get the primary key column name used in the subtype table.
     *
     * @return string
     */
    public function getSubtypeKeyName(): string 
    {
        return $this->subtypeKeyName ?? $this->getKeyName();
    }

    /**
     * Fire a model event with the given name.
     *
     * @param string $event Name of the event
     * @param bool $halt Whether to halt if the event returns false
     * @return mixed
     */
    protected function fireModelEvent(string $event, bool $halt = true): mixed
    {
        if (! isset($this->dispatchesEvents[$event])) {
            return parent::fireModelEvent($event, $halt);
        }

        $method = $halt ? 'until' : 'dispatch';

        return static::$dispatcher->{$method}(new $this->dispatchesEvents[$event]($this));
    }
}
