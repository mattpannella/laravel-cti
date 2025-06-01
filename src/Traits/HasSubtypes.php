<?php

namespace Pannella\Cti\Traits;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Exceptions\SubtypeException;
use Pannella\Cti\Support\SubtypedCollection;

/**
 * Trait for implementing Class Table Inheritance in base/parent models.
 * 
 * This trait provides functionality for resolving models to their specific
 * subtypes based on a type identifier. It requires the using class to define
 * several static properties to configure the subtype resolution.
 *
 * Required static properties:
 * @property-read array $subtypeMap Mapping of type labels to subtype class names
 * @property-read string $subtypeKey Column name containing the type identifier
 * @property-read string $subtypeLookupTable Table name containing type definitions
 * @property-read string $subtypeLookupKey Primary key column in lookup table
 * @property-read string $subtypeLookupLabel Column containing type label in lookup table
 */
trait HasSubtypes
{
    /**
     * Override newFromBuilder to morph base model to subtype based on type_id.
     * 
     * When a model is loaded from the database, this method checks if it should
     * be converted to a more specific subtype based on its type identifier.
     *
     * @param array $attributes The model attributes from the database
     * @param string|null $connection The database connection name
     * @return static|\Illuminate\Database\Eloquent\Model
     */
    public function newFromBuilder($attributes = [], $connection = null): static
    {
        $instance = parent::newFromBuilder($attributes, $connection);

        $typeId = data_get($attributes, static::$subtypeKey);
        $label = $typeId ? static::resolveSubtypeLabel($typeId) : null;
        $subclass = $label ? static::$subtypeMap[$label] ?? null : null;

        if ($subclass && is_subclass_of($subclass, static::class)) {
            //instantiate subtype and fill raw attributes
            $sub = (new $subclass)->newInstance([], true);
            $sub->setRawAttributes((array) $attributes, true);
            $sub->exists = true;

            if (method_exists($sub, 'loadSubtypeData')) {
                $sub->loadSubtypeData();
            }

            return $sub;
        }

        return $instance;
    }

    /**
     * Resolve subtype label for a given type_id from the lookup table.
     *
     * @param int|string $typeId The type identifier to resolve
     * @return string|null The resolved type label or null if not found
     * @throws SubtypeException When lookup table is not configured or resolution fails
     */
    public static function resolveSubtypeLabel(int|string $typeId): ?string
    {
        static $cache = [];

        if (!$typeId) {
            return null;
        }

        if (isset($cache[$typeId])) {
            return $cache[$typeId];
        }

        if (!static::$subtypeLookupTable) {
            throw SubtypeException::missingLookupTable();
        }

        try {
            $instance = new static();
            $type = $instance->getConnection()->table(static::$subtypeLookupTable)
                ->where(static::$subtypeLookupKey, $typeId)
                ->first();

            $label = $type->{static::$subtypeLookupLabel} ?? null;
            
            if (!$label) {
                throw SubtypeException::invalidSubtype((string) $typeId);
            }

            $cache[$typeId] = $label;
            return $label;
        } catch (\Exception $e) {
            if ($e instanceof SubtypeException) {
                throw $e;
            }
            throw new SubtypeException("Failed to resolve subtype: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Load subtype data for a collection or single model.
     * Groups models by subtype, queries subtype tables in batches to avoid N+1.
     *
     * @return \Illuminate\Support\Collection
     */
    public function loadSubtypes(): Collection
    {
        //support both a Collection or single model instance
        $collection = $this instanceof Collection ? $this : collect([$this]);

        //group models by their subtype label, so we can eager load subtype data in batches
        $grouped = $collection->groupBy(fn ($model) => $model->getSubtypeLabel());

        foreach ($grouped as $label => $models) {
            $class = static::$subtypeMap[$label] ?? null;
            if (!$class) {
                continue;
            }

            $instance = new $class;

            $table = $instance->getTable();
            $keyName = $instance->getKeyName();

            //collect keys to query subtype data in batch
            $keys = $models->pluck($keyName)->all();

            $subdata = $this->getConnection()->table($table)->whereIn($keyName, $keys)->get()->keyBy($keyName);

            foreach ($models as $model) {
                $extra = $subdata[$model->getKey()] ?? null;
                if ($extra) {
                    //merge subtype attributes into the model
                    $model->fill((array) $extra);
                }
            }
        }

        return $collection;
    }

    /**
     * Get subtype label for this model.
     */
    public function getSubtypeLabel(): ?string
    {
        $typeId = $this->{static::$subtypeKey} ?? null;
        return static::resolveSubtypeLabel($typeId);
    }

    /**
     * Get the mapping of subtype labels to their corresponding class names.
     *
     * @return array<string, string> Array of label => classname pairs
     */
    public function getSubtypeMap(): array
    {
        return static::$subtypeMap ?? [];
    }

    /**
     * Create a new collection instance with subtype support.
     * 
     * @param array $models Array of models to include in collection
     * @return \Pannella\Cti\Support\SubtypedCollection
     */
    public function newCollection(array $models = []): SubtypedCollection
    {
        return new SubtypedCollection($models);
    }
}