<?php

namespace Pannella\Cti\Traits;

use Illuminate\Container\Container;
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
 *
 * Optional static properties (required only when using a lookup table):
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
     * Uses container-scoped caching (safe for Octane/multi-tenant) and
     * batch-loads all rows from the lookup table on first access to avoid
     * N+1 queries when resolving multiple subtypes.
     *
     * @param int|string $typeId The type identifier to resolve
     * @return string|null The resolved type label or null if not found
     * @throws SubtypeException When lookup table is not configured or resolution fails
     */
    public static function resolveSubtypeLabel(int|string $typeId): ?string
    {
        if (!$typeId) {
            return null;
        }

        try {
            $instance = new static();

            // Direct mode: discriminator column contains the label directly
            if (!$instance->usesLookupTable()) {
                return (string) $typeId;
            }

            $lookupTable = $instance->getSubtypeLookupTable();

            if (!$lookupTable) {
                throw SubtypeException::missingLookupTable();
            }

            $connectionName = $instance->getConnectionName() ?? 'default';
            $databaseName = $instance->getConnection()->getDatabaseName();
            $cacheKey = "cti.lookup.{$connectionName}.{$databaseName}.{$lookupTable}";

            $cache = Container::getInstance()->has($cacheKey)
                ? Container::getInstance()->make($cacheKey)
                : null;

            if (isset($cache[$typeId])) {
                return $cache[$typeId];
            }

            $lookupKey = $instance->getSubtypeLookupKey();
            $lookupLabel = $instance->getSubtypeLookupLabel();

            // Load all rows from the lookup table at once to avoid N+1 queries
            $cache = $cache ?? [];
            $types = $instance->getConnection()->table($lookupTable)->get();

            foreach ($types as $type) {
                $cache[$type->{$lookupKey}] = $type->{$lookupLabel};
            }

            Container::getInstance()->instance($cacheKey, $cache);

            $label = $cache[$typeId] ?? null;

            if (!$label) {
                throw SubtypeException::invalidSubtype((string) $typeId);
            }

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
     * Determine whether this model uses a lookup table for type resolution.
     *
     * When true, the discriminator column contains a foreign key to a lookup table.
     * When false, the discriminator column contains the type label directly.
     *
     * @return bool
     */
    public function usesLookupTable(): bool
    {
        if (isset(static::$subtypeLookupTable)) {
            return true;
        }

        $attr = CtiAttributeResolver::resolveSubtypeConfig(static::class);

        return $attr !== null && $attr->lookupTable !== null;
    }

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
     * @return string|null Returns null when no lookup table is configured (direct discriminator mode).
     */
    public function getSubtypeLookupTable(): ?string
    {
        if (isset(static::$subtypeLookupTable)) {
            return static::$subtypeLookupTable;
        }

        $attr = CtiAttributeResolver::resolveSubtypeConfig(static::class);
        if ($attr && $attr->lookupTable !== null) {
            return $attr->lookupTable;
        }

        return null;
    }

    /**
     * Get the static subtype lookup key name (PK in lookup table).
     * @return string|null Returns null when no lookup table is configured (direct discriminator mode).
     */
    public function getSubtypeLookupKey(): ?string
    {
        if (isset(static::$subtypeLookupKey)) {
            return static::$subtypeLookupKey;
        }

        $attr = CtiAttributeResolver::resolveSubtypeConfig(static::class);
        if ($attr && $attr->lookupKey !== null) {
            return $attr->lookupKey;
        }

        return null;
    }

    /**
     * Get the static subtype lookup label name (label column in lookup table).
     * @return string|null Returns null when no lookup table is configured (direct discriminator mode).
     */
    public function getSubtypeLookupLabel(): ?string
    {
        if (isset(static::$subtypeLookupLabel)) {
            return static::$subtypeLookupLabel;
        }

        $attr = CtiAttributeResolver::resolveSubtypeConfig(static::class);
        if ($attr && $attr->lookupLabel !== null) {
            return $attr->lookupLabel;
        }

        return null;
    }
}
