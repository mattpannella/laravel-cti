<?php

namespace Pannella\Cti\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\QueryException;
use Pannella\Cti\Exceptions\SubtypeException;
use Pannella\Cti\SubtypeModel;

/**
 * Global scope that filters queries to only return records matching the subtype's discriminator value.
 *
 * This scope automatically adds a WHERE clause to filter by the correct type_id value
 * when querying subtype models (e.g., Quiz, Survey).
 */
class SubtypeDiscriminatorScope implements Scope
{
    /**
     * Cache of resolved type IDs per model class.
     * Only successful (non-null) resolutions are cached, so a failed
     * resolution is retried on the next query instead of pinning the
     * scope to an empty result set for the life of the process.
     *
     * @var array<class-string, int|string>
     */
    protected static array $typeIdCache = [];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! $model instanceof SubtypeModel) {
            return;
        }

        // Only apply if model has a parent CTI class configured
        $ctiParentClass = $model->getCtiParentClass();
        if (empty($ctiParentClass)) {
            return;
        }

        $typeId = $this->resolveTypeId($model);

        if ($typeId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $parentInstance = new $ctiParentClass;
        $discriminatorColumn = $parentInstance->getSubtypeKey();

        // Add WHERE clause to filter by discriminator
        $builder->where($model->getTable().'.'.$discriminatorColumn, '=', $typeId);
    }

    /**
     * Resolve the type ID for the given subtype model.
     * Uses caching to avoid repeated lookups.
     */
    protected function resolveTypeId(SubtypeModel $model): int|string|null
    {
        $modelClass = get_class($model);

        // Return cached value if available
        if (array_key_exists($modelClass, static::$typeIdCache)) {
            return static::$typeIdCache[$modelClass];
        }

        try {
            $ctiParentClass = $model->getCtiParentClass();

            if (! $ctiParentClass || ! class_exists($ctiParentClass)) {
                return null;
            }

            $parentInstance = new $ctiParentClass;
            $subtypeMap = $parentInstance->getSubtypeMap();

            // Find the label for this model class in the subtype map
            $label = array_search($modelClass, $subtypeMap, true);

            if ($label === false) {
                return null;
            }

            // Direct mode: use the label string as the discriminator value
            if (! $parentInstance->usesLookupTable()) {
                static::$typeIdCache[$modelClass] = $label;

                return $label;
            }

            // Look up the type ID from the lookup table
            $lookupTable = $parentInstance->getSubtypeLookupTable();
            $lookupKeyCol = $parentInstance->getSubtypeLookupKey();
            $lookupLabelCol = $parentInstance->getSubtypeLookupLabel();

            $typeId = $model->getConnection()->table($lookupTable)
                ->where($lookupLabelCol, $label)
                ->value($lookupKeyCol);

            if ($typeId !== null) {
                static::$typeIdCache[$modelClass] = $typeId;
            }

            return $typeId;
        } catch (QueryException $e) {
            // Table doesn't exist yet (e.g. during migrations) — don't cache, retry on next query
            return null;
        } catch (SubtypeException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new SubtypeException("Failed to resolve discriminator type: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Clear the type ID cache.
     * Useful for testing or when type definitions change at runtime.
     */
    public static function clearCache(): void
    {
        static::$typeIdCache = [];
    }
}
