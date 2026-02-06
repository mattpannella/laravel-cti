<?php

namespace Pannella\Cti;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Pannella\Cti\Exceptions\SubtypeException;
use Pannella\Cti\Support\SubtypedCollection;
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
 * @property array<int, string> $subtypeAttributes List of attributes that belong to the subtype table
 * @property string|null $subtypeKeyName Foreign key column name in subtype table
 *
 */
abstract class SubtypeModel extends Model
{
    use HasSubtypeRelations;
    use BootsSubtypeModel;

    public const EVENT_SUBTYPE_SAVING = 'subtypeSaving';
    public const EVENT_SUBTYPE_SAVED = 'subtypeSaved';
    public const EVENT_SUBTYPE_DELETING = 'subtypeDeleting';
    public const EVENT_SUBTYPE_DELETED = 'subtypeDeleted';

    /**
     * @var string|null
     */
    protected $subtypeTable;

    /**
     * @var array<int, string>
     */
    protected $subtypeAttributes = [];

    /**
     * @var string|null
     */
    protected $subtypeKeyName;

    /**
     * @var class-string|null
     */
    protected $ctiParentClass;

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [];

    /**
     * Create a new model instance from builder results.
     * Merges parent model casts if $ctiParentClass is defined.
     *
     * @param array<string, mixed> $attributes
     * @param string|null $connection
     * @return static
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $model = parent::newFromBuilder($attributes, $connection);

        // Merge parent casts if parent class is defined
        if ($model->ctiParentClass && class_exists($model->ctiParentClass)) {
            $parent = new $model->ctiParentClass();
            $model->mergeCasts($parent->getCasts());

            // Apply casts to loaded attributes
            foreach ((array)$attributes as $key => $value) {
                if ($model->hasCast($key)) {
                    $model->setAttribute($key, $value);
                }
            }
        }

        return $model;
    }

    /**
     * Save both parent and subtype data inside a database transaction.
     *
     * @param array<string, mixed> $options Save options passed to parent save method
     * @return bool Whether the save was successful
     * @throws SubtypeException When saving subtype data fails
     */
    public function save(array $options = []): bool
    {
        //if subtypeTable is not defined, or no subtypeAttributes, treat as a normal model save.
        if (empty($this->subtypeTable) || empty($this->subtypeAttributes)) {
            return parent::save($options);
        }
        return $this->getConnection()->transaction(function () use ($options) {
            if ($this->fireModelEvent('subtypeSaving') === false) {
                return false;
            }

            //store original state
            $originalAttributesArray = $this->attributes;
            $dirtyAttributes = $this->getDirty();

            //split dirty attributes
            $dirtyParentAttributes = array_intersect_key(
                $dirtyAttributes,
                $this->getParentAttributes()
            );

            $dirtySubtypeAttributes = array_intersect_key(
                $dirtyAttributes,
                array_flip($this->getSubtypeAttributes())
            );

            //force save parent record even if only subtype attributes are dirty
            //this ensures we have a primary key for the subtype record
            $this->attributes = $this->getParentAttributes();
            $saved = parent::save($options);

            if ($saved) {
                //get any changes from parent save
                $parentSaveChanges = array_diff_key($this->attributes, $originalAttributesArray);

                //restore full attribute set
                $this->attributes = array_merge(
                    $originalAttributesArray,
                    $parentSaveChanges
                );

                //save subtype data if we have any
                if (!empty($dirtySubtypeAttributes)) {
                    $this->saveSubtypeData();
                }

                //reload data to ensure consistency
                if ($this->ctiParentClass && class_exists($this->ctiParentClass)) {
                    $parentModel = (new $this->ctiParentClass)->newQuery()
                        ->where($this->getKeyName(), $this->getKey())
                        ->first();

                    if ($parentModel) {
                        foreach ($parentModel->getAttributes() as $key => $value) {
                            if (!in_array($key, $this->getSubtypeAttributes())) {
                                $this->setAttribute($key, $value);
                            }
                        }
                    }
                }

                $this->loadSubtypeData();
                //sync original attributes after loading all data
                $this->syncOriginal();
                $this->fireModelEvent('subtypeSaved');
            } else {
                //restore original state if save failed
                $this->attributes = $originalAttributesArray;
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
            // Get the primary key column name
            $keyName = $this->subtypeKeyName ?? $this->getKeyName();
            $key = $this->getKey();

            if (!$key) {
                throw SubtypeException::missingTypeId(static::class);
            }

            // Filter model attributes to only include subtype attributes
            $data = array_intersect_key($this->getAttributes(), array_flip($this->subtypeAttributes));

            //check if a record already exists for this model in the subtype table
            if ($this->getConnection()->table($this->subtypeTable)->where($keyName, $key)->exists()) {
                $this->getConnection()->table($this->subtypeTable)
                    ->where($keyName, $key)
                    ->update($data);
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

                $this->getConnection()->table($this->subtypeTable)
                    ->where($keyName, $this->getKey())
                    ->delete();

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
     * Create a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return \Pannella\Cti\SubtypeQueryBuilder<static>
     */
    public function newEloquentBuilder($query): SubtypeQueryBuilder
    {
        /** @var SubtypeQueryBuilder<static> */
        return new SubtypeQueryBuilder($query);
    }

    /**
     * Get the list of attributes that belong to the subtype table.
     *
     * @return array<int, string>
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

    /**
     * Create a new instance of the given model.
     *
     * @param array<string, mixed> $attributes
     * @param bool $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        //get the normal new instance
        $instance = parent::newInstance($attributes, $exists);

        //if this is an existing record, load its subtype data
        if ($exists && $instance->getKey()) {
            $instance->loadSubtypeData();
        }

        return $instance;
    }

    /**
     * Create a new collection instance with subtype support.
     *
     * @param array<int, \Illuminate\Database\Eloquent\Model> $models Array of models to include in collection
     * @return \Pannella\Cti\Support\SubtypedCollection<int, static>
     */
    public function newCollection(array $models = [])
    {
        /** @var SubtypedCollection<int, static> */
        return new SubtypedCollection($models);
    }

    /**
     * Create a new instance of the model being queried.
     * Overridden to ensure subtype configuration is preserved.
     *
     * @param array<string, mixed> $attributes
     * @return static
     */
    public function newModelInstance($attributes = [])
    {
        $model = parent::newInstance($attributes);

        //if we're copying an existing model's data, load its subtype data
        if (!empty($attributes) && isset($attributes[$this->getKeyName()])) {
            $model->loadSubtypeData();
        }

        return $model;
    }

    /**
     * Create a copy of the model.
     *
     * @param array<int, string>|null $except
     * @return static
     */
    public function replicate(?array $except = [])
    {
        //ensure the subtype foreign key is in the $except array
        if (!$except) {
            $except = [];
        }

        //add the subtype's foreign key to $except
        $subtypeKeyName = $this->getSubtypeKeyName();
        if (!in_array($subtypeKeyName, $except)) {
            $except[] = $subtypeKeyName;
        }

        //get base model replica
        $instance = parent::replicate($except);

        //copy subtype attributes except those in $except
        $subtypeAttributes = array_diff($this->getSubtypeAttributes(), $except);
        foreach ($subtypeAttributes as $attribute) {
            //skip the foreign key column
            if ($attribute !== $subtypeKeyName) {
                $instance->setAttribute($attribute, $this->getAttribute($attribute));
            }
        }

        return $instance;
    }

    /**
     * Reload the current model instance with fresh attributes from the database.
     *
     * @return $this
     */
    public function refresh()
    {
        parent::refresh();

        //after refreshing parent data, reload subtype data
        if ($this->exists) {
            $this->loadSubtypeData();
        }

        return $this;
    }

    /**
     * Register a subtype saved event listener with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function subtypeSaved($callback)
    {
        static::registerModelEvent('subtypeSaved', $callback);
    }

    /**
     * Register a subtype saving event listener with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function subtypeSaving($callback)
    {
        static::registerModelEvent('subtypeSaving', $callback);
    }

    /**
     * Register a subtype deleting event listener with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function subtypeDeleting($callback)
    {
        static::registerModelEvent('subtypeDeleting', $callback);
    }

    /**
     * Register a subtype deleted event listener with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function subtypeDeleted($callback)
    {
        static::registerModelEvent('subtypeDeleted', $callback);
    }

    /**
     * Get parent attributes excluding subtype attributes.
     *
     * @return array<string, mixed>
     */
    protected function getParentAttributes(): array
    {
        $excludeColumns = $this->subtypeAttributes;
        if ($this->subtypeKeyName) {
            $excludeColumns[] = $this->subtypeKeyName;
        }

        return array_diff_key(
            $this->getAttributes(),
            array_flip($excludeColumns)
        );
    }

    /**
     * Handle dynamic method calls to proxy parent model relationships.
     * If the method doesn't exist on this model but exists on the parent CTI class,
     * proxy the call to the parent model to access parent relationships.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        // First, try to handle via parent Model class (scopes, relationships, etc.)
        try {
            return parent::__call($method, $parameters);
        } catch (\BadMethodCallException $e) {
            // If parent Model couldn't handle it, try parent CTI class
            if ($this->ctiParentClass && class_exists($this->ctiParentClass)) {
                $parentModel = new $this->ctiParentClass();

                // Transfer state to parent model so relationships work correctly
                $parentModel->setRawAttributes($this->getAttributes(), true);
                $parentModel->exists = $this->exists;
                $parentModel->setConnection($this->getConnectionName());

                // Check if method exists and call it
                if (method_exists($parentModel, $method)) {
                    $result = $parentModel->$method(...$parameters);

                    // If it's a relation, return it (relationships return Relation instances)
                    if ($result instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                        return $result;
                    }

                    // For non-relation results, also return them
                    return $result;
                }
            }

            // Re-throw original exception if we couldn't handle it
            throw $e;
        }
    }
}
