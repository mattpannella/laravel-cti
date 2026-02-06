<?php

namespace Pannella\Cti\Traits;

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

        $typeId = data_get($attributes, static::$subtypeKey);
        $label = $typeId ? static::resolveSubtypeLabel($typeId) : null;
        $subclass = $label ? static::$subtypeMap[$label] ?? null : null;

        if ($subclass && class_exists($subclass)) {
            // Create subtype instance with parent's casts applied
            $sub = new $subclass();
            $sub->mergeCasts($this->getCasts());
            $sub = $sub->newFromBuilder($attributes, $connection);

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

        if (!static::$subtypeLookupTable) {
            throw SubtypeException::missingLookupTable();
        }

        try {
            $instance = new static();
            $connectionName = $instance->getConnectionName() ?? 'default';

            if (isset($cache[$connectionName][$typeId])) {
                return $cache[$connectionName][$typeId];
            }

            $type = $instance->getConnection()->table(static::$subtypeLookupTable)
                ->where(static::$subtypeLookupKey, $typeId)
                ->first();

            $label = $type->{static::$subtypeLookupLabel} ?? null;

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
        $typeId = $this->{static::$subtypeKey} ?? null;
        return static::resolveSubtypeLabel($typeId);
    }

    /**
     * Get the mapping of subtype labels to their corresponding class names (instance method).
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

    // New Public Static Accessors

    /**
     * Get the subtype key (discriminator column name).
     * @return string
     * @throws SubtypeException If not defined.
     */
    public function getSubtypeKey(): string
    {
        if (!isset(static::$subtypeKey)) {
            throw SubtypeException::missingConfiguration(static::class, 'subtypeKey');
        }
        return static::$subtypeKey;
    }

    /**
     * Get the static subtype lookup table name.
     * @return string
     * @throws SubtypeException If not defined.
     */
    public static function getSubtypeLookupTable(): string
    {
        if (!isset(static::$subtypeLookupTable)) {
            throw SubtypeException::missingConfiguration(static::class, 'subtypeLookupTable');
        }
        return static::$subtypeLookupTable;
    }

    /**
     * Get the static subtype lookup key name (PK in lookup table).
     * @return string
     * @throws SubtypeException If not defined.
     */
    public static function getSubtypeLookupKey(): string
    {
        if (!isset(static::$subtypeLookupKey)) {
            throw SubtypeException::missingConfiguration(static::class, 'subtypeLookupKey');
        }
        return static::$subtypeLookupKey;
    }

    /**
     * Get the static subtype lookup label name (label column in lookup table).
     * @return string
     * @throws SubtypeException If not defined.
     */
    public static function getSubtypeLookupLabel(): string
    {
        if (!isset(static::$subtypeLookupLabel)) {
            throw SubtypeException::missingConfiguration(static::class, 'subtypeLookupLabel');
        }
        return static::$subtypeLookupLabel;
    }
}