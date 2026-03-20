<?php

namespace Pannella\Cti\Traits;

use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Attributes\CtiAttributeResolver;
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
 * @property-read array<string, class-string> $subtypeMap Mapping of type labels to subtype class names
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
    public function newFromBuilder($attributes = [], $connection = null): Model
    {
        $instance = parent::newFromBuilder($attributes, $connection);

        $subtypeKey = $this->getSubtypeKey();
        $typeId = data_get($attributes, $subtypeKey);
        $label = $typeId ? static::resolveSubtypeLabel($typeId) : null;
        $subtypeMap = $this->getSubtypeMap();
        $subclass = $label ? $subtypeMap[$label] ?? null : null;

        if ($subclass && class_exists($subclass)) {
            // Create subtype instance with parent's casts applied
            // We use newInstance with $exists=true to skip individual loadSubtypeData()
            // because SubtypedCollection will batch-load all subtype data efficiently
            $sub = (new $subclass())->newInstance([], true);
            $sub->mergeCasts($this->getCasts());
            $sub->setRawAttributes((array) $attributes, true);

            // Manually apply casts to the attributes that were set
            foreach ($attributes as $key => $value) {
                if ($sub->hasCast($key)) {
                    $sub->setAttribute($key, $value);
                }
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

        try {
            $instance = new static();
            $lookupTable = $instance->getSubtypeLookupTable();

            if (!$lookupTable) {
                throw SubtypeException::missingLookupTable();
            }

            $connectionName = $instance->getConnectionName() ?? 'default';

            if (isset($cache[$connectionName][$typeId])) {
                return $cache[$connectionName][$typeId];
            }

            $lookupKey = $instance->getSubtypeLookupKey();
            $lookupLabel = $instance->getSubtypeLookupLabel();

            $type = $instance->getConnection()->table($lookupTable)
                ->where($lookupKey, $typeId)
                ->first();

            $label = $type?->{$lookupLabel};

            if (!$label) {
                throw SubtypeException::invalidSubtype((string) $typeId);
            }

            $cache[$connectionName][$typeId] = $label;
            return $label;
        } catch (\Exception $e) {
            if ($e instanceof SubtypeException) {
                throw $e;
            }
            throw new SubtypeException("Failed to resolve subtype: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get subtype label for this model.
     */
    public function getSubtypeLabel(): ?string
    {
        $typeId = $this->{$this->getSubtypeKey()} ?? null;
        return static::resolveSubtypeLabel($typeId);
    }

    /**
     * Get the mapping of subtype labels to their corresponding class names (instance method).
     *
     * @return array<string, string> Array of label => classname pairs
     */
    public function getSubtypeMap(): array
    {
        if (isset(static::$subtypeMap)) {
            return static::$subtypeMap;
        }

        $attr = CtiAttributeResolver::resolveSubtypeConfig(static::class);
        return $attr ? $attr->map : [];
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

    // New Public Static Accessors

    /**
     * Get the subtype key (discriminator column name).
     * @return string
     * @throws SubtypeException If not defined.
     */
    public function getSubtypeKey(): string
    {
        if (isset(static::$subtypeKey)) {
            return static::$subtypeKey;
        }

        $attr = CtiAttributeResolver::resolveSubtypeConfig(static::class);
        if ($attr) {
            return $attr->key;
        }

        throw SubtypeException::missingConfiguration(static::class, 'subtypeKey');
    }

    /**
     * Get the static subtype lookup table name.
     * @return string
     * @throws SubtypeException If not defined.
     */
    public function getSubtypeLookupTable(): string
    {
        if (isset(static::$subtypeLookupTable)) {
            return static::$subtypeLookupTable;
        }

        $attr = CtiAttributeResolver::resolveSubtypeConfig(static::class);
        if ($attr) {
            return $attr->lookupTable;
        }

        throw SubtypeException::missingConfiguration(static::class, 'subtypeLookupTable');
    }

    /**
     * Get the static subtype lookup key name (PK in lookup table).
     * @return string
     * @throws SubtypeException If not defined.
     */
    public function getSubtypeLookupKey(): string
    {
        if (isset(static::$subtypeLookupKey)) {
            return static::$subtypeLookupKey;
        }

        $attr = CtiAttributeResolver::resolveSubtypeConfig(static::class);
        if ($attr) {
            return $attr->lookupKey;
        }

        throw SubtypeException::missingConfiguration(static::class, 'subtypeLookupKey');
    }

    /**
     * Get the static subtype lookup label name (label column in lookup table).
     * @return string
     * @throws SubtypeException If not defined.
     */
    public function getSubtypeLookupLabel(): string
    {
        if (isset(static::$subtypeLookupLabel)) {
            return static::$subtypeLookupLabel;
        }

        $attr = CtiAttributeResolver::resolveSubtypeConfig(static::class);
        if ($attr) {
            return $attr->lookupLabel;
        }

        throw SubtypeException::missingConfiguration(static::class, 'subtypeLookupLabel');
    }
}
