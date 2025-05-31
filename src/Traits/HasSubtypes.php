<?php

namespace Pannella\Cti\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

trait HasSubtypes
{
    // Map of subtype labels to model class names
    protected static $subtypeMap = [];

    // Column on parent table to discriminate subtype
    protected static $subtypeKey = 'type_id';

    // Lookup table and keys for mapping type_id to label
    protected static $subtypeLookupTable = null;
    protected static $subtypeLookupKey = 'id';
    protected static $subtypeLookupLabel = 'label';

    /**
     * Override newFromBuilder to morph base model to subtype based on type_id.
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        // First, create base model instance
        $instance = parent::newFromBuilder($attributes, $connection);

        $typeId = data_get($attributes, static::$subtypeKey);
        $label = $typeId ? static::resolveSubtypeLabel($typeId) : null;
        $subclass = $label ? static::$subtypeMap[$label] ?? null : null;

        if ($subclass && is_subclass_of($subclass, static::class)) {
            // Instantiate subtype and fill raw attributes
            $sub = (new $subclass)->newInstance([], true);
            $sub->setRawAttributes((array) $attributes, true);
            $sub->exists = true;

            // Optionally load subtype data here if you want full eager loading
            if (method_exists($sub, 'loadSubtypeData')) {
                $sub->loadSubtypeData();
            }

            return $sub;
        }

        return $instance;
    }

    /**
     * Resolve subtype label for a given type_id from the lookup table.
     * Cache results statically to reduce DB hits on repeated calls.
     */
    public static function resolveSubtypeLabel($typeId)
    {
        static $cache = [];

        if (!$typeId) {
            return null;
        }

        if (isset($cache[$typeId])) {
            return $cache[$typeId];
        }

        if (!static::$subtypeLookupTable) {
            throw new \RuntimeException("Subtypes require a defined lookup table.");
        }

        $type = DB::table(static::$subtypeLookupTable)
            ->where(static::$subtypeLookupKey, $typeId)
            ->first();

        $label = $type->{static::$subtypeLookupLabel} ?? null;
        $cache[$typeId] = $label;

        return $label;
    }

    /**
     * Load subtype data for a collection or single model.
     * Groups models by subtype, queries subtype tables in batches to avoid N+1.
     */
    public function loadSubtypes()
    {
        // Support both a Collection or single model instance
        $collection = $this instanceof Collection ? $this : collect([$this]);

        // Group models by their subtype label
        $grouped = $collection->groupBy(fn ($model) => $model->getSubtypeLabel());

        foreach ($grouped as $label => $models) {
            $class = static::$subtypeMap[$label] ?? null;
            if (!$class) continue;

            $instance = new $class;

            $table = $instance->getTable();
            $keyName = $instance->getKeyName();

            // Collect keys to query subtype data in batch
            $keys = $models->pluck($keyName)->all();

            $subdata = DB::table($table)->whereIn($keyName, $keys)->get()->keyBy($keyName);

            foreach ($models as $model) {
                $extra = $subdata[$model->getKey()] ?? null;
                if ($extra) {
                    // Merge subtype attributes into the model
                    $model->fill((array) $extra);
                }
            }
        }

        return $collection;
    }

    /**
     * Get subtype label for this model.
     */
    public function getSubtypeLabel()
    {
        $typeId = $this->{static::$subtypeKey} ?? null;
        return static::resolveSubtypeLabel($typeId);
    }
}
