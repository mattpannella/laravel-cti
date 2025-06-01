<?php

namespace Pannella\Cti;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Pannella\Cti\Exceptions\SubtypeException;
use Pannella\Cti\Traits\HasSubtypeRelations;
use Pannella\Cti\Traits\BootsSubtypeModel;

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
    use BootsSubtypeModel; // Use the new trait

    public const EVENT_SUBTYPE_SAVING = 'subtypeSaving';
    public const EVENT_SUBTYPE_SAVED = 'subtypeSaved';
    public const EVENT_SUBTYPE_DELETING = 'subtypeDeleting';
    public const EVENT_SUBTYPE_DELETED = 'subtypeDeleted';

    //name of the subtype table (e.g. assessment_quiz)
    protected $subtypeTable;

    //attributes that belong to the subtype table
    protected $subtypeAttributes = [];

    //optionally override if subtype PK column name differs from parent PK
    protected $subtypeKeyName;

    //required to be able to create new records in the supertype table
    protected $ctiParentClass;

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

    // The boot() method is removed from here, as the trait's bootBootsSubtypeModel will be called.

    /**
     * Save both parent and subtype data inside a database transaction.
     *
     * @param array $options Save options passed to parent save method
     * @return bool Whether the save was successful
     * @throws SubtypeException When saving subtype data fails
     */
    public function save(array $options = []): bool
    {
        // If subtypeTable is not defined, or no subtypeAttributes, treat as a normal model save.
        if (empty($this->subtypeTable) || empty($this->subtypeAttributes)) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options) {
            if ($this->fireModelEvent('subtypeSaving') === false) {
                return false;
            }

            // Get all attributes currently set on the model
            $originalAttributes = $this->getAttributes();
            
            // Filter out subtype-specific attributes to get only parent attributes
            // These are the attributes that should be saved to the base/parent table.
            // The 'type_id' should now be set by the 'creating' event listener if it was a new model.
            $parentAttributes = array_diff_key($originalAttributes, array_flip($this->getSubtypeAttributes()));
            
            // Temporarily set the model's attributes to *only* parent attributes.
            // This ensures parent::save() only attempts to save these to the base table.
            $this->attributes = $parentAttributes;
            
            // Preserve the model's original "exists" status, as manipulating attributes might affect it
            // before parent::save() correctly determines and sets it.
            // However, parent::save() will correctly set $this->exists.

            // Save the parent model data.
            // This will handle inserting/updating the base table record and updating timestamps,
            // auto-incrementing ID, etc., using only the $parentAttributes.
            $saved = parent::save($options);

            // After parent::save(), $this->attributes will contain the state of the parent record
            // (e.g., with ID and timestamps if it was an insert).
            // $this->exists will also be correctly updated by parent::save().
            $attributesAfterParentSave = $this->getAttributes();
            $currentExistsStatus = $this->exists;

            // Restore the original full set of attributes (parent + subtype) to the model.
            // This ensures that when saveSubtypeData() is called, it has access to all subtype attributes.
            $this->attributes = $originalAttributes;
            
            // Now, merge in any changes from the parent save (like the new ID or updated timestamps)
            // back into the model's attributes. This ensures the model object is consistent.
            foreach ($attributesAfterParentSave as $key => $value) {
                $this->setAttribute($key, $value);
            }
            
            // Restore the 'exists' status that was set by parent::save().
            $this->exists = $currentExistsStatus;

            if ($saved) {
                // Now that the parent record is saved (and we have its ID), save the subtype data.
                $this->saveSubtypeData();
                $this->fireModelEvent('subtypeSaved');
            } else {
                // If parent save failed, restore original attributes and exists status
                // to leave the model in its pre-save state as much as possible.
                // (The transaction will also rollback, but this keeps the model instance consistent).
                $this->attributes = $originalAttributes;
                // $this->exists would be false if save failed on a new model, or original state if update failed.
                // For simplicity, we rely on the transaction to revert DB changes.
                // The model state restoration here is for the object in memory.
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
            //get the primary key column name. use subtype override if exists, otherwise use parent key
            $keyName = $this->subtypeKeyName ?? $this->getKeyName();
            $key = $this->getKey();

            if (!$key) {
                throw SubtypeException::missingTypeId(static::class);
            }

            //filter model attributes to only include those designated as subtype attributes
            //this ensures we only save relevant columns to the subtype table
            $data = array_intersect_key($this->getAttributes(), array_flip($this->subtypeAttributes));

            //check if a record already exists for this model in the subtype table
            if ($this->getConnection()->table($this->subtypeTable)->where($keyName, $key)->exists()) {
                $updated = $this->getConnection()->table($this->subtypeTable)
                    ->where($keyName, $key)
                    ->update($data);
                
                if ($updated === false) {
                    throw SubtypeException::saveFailed($this->subtypeTable);
                }
            } else {
                //insert a new record
                //merge the primary key into the data array to maintain the relationship
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
    public function newEloquentBuilder($query): SubtypeQueryBuilder
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
    protected function fireModelEvent($event, $halt = true): mixed
    {
        if (! isset($this->dispatchesEvents[$event])) {
            return parent::fireModelEvent($event, $halt);
        }

        $method = $halt ? 'until' : 'dispatch';

        return static::$dispatcher->{$method}(new $this->dispatchesEvents[$event]($this));
    }
}