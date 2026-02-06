<?php

namespace Pannella\Cti\Support;

use Illuminate\Database\Eloquent\Collection;
use Pannella\Cti\SubtypeModel;

/**
 * Collection class with support for batch-loading subtype data.
 *
 * Extends Laravel's Collection to automatically load subtype-specific
 * data for all models efficiently using batch queries instead of N+1.
 *
 * @template TKey of array-key
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends \Illuminate\Database\Eloquent\Collection<TKey, TModel>
 */
class SubtypedCollection extends Collection
{
    /**
     * @param array|mixed $items
     */
    public function __construct($items = [])
    {
        parent::__construct($items);
        $this->loadSubtypes();
    }

    /**
     * Batch-load subtype data for all SubtypeModel instances in the collection.
     *
     * Groups models by their concrete class and loads subtype-specific data
     * from each subtype table in a single query per subtype, then fills each
     * model with its subtype attributes.
     *
     * Uses plain arrays internally to avoid creating new SubtypedCollection
     * instances (which would trigger infinite recursion via the constructor).
     *
     * @return $this
     */
    public function loadSubtypes(): static
    {
        if ($this->isEmpty()) {
            return $this;
        }

        // Group eligible models by concrete class using a plain array
        // to avoid filter()/groupBy() returning new SubtypedCollection instances
        $grouped = [];

        foreach ($this->items as $model) {
            if (
                $model instanceof SubtypeModel
                && $model->getSubtypeTable()
                && $model->getKey()
            ) {
                // Check if subtype data is already loaded
                // If any subtype attribute is set, assume data is already loaded
                $subtypeAttrs = $model->getSubtypeAttributes();
                $alreadyLoaded = false;
                if (!empty($subtypeAttrs)) {
                    foreach ($subtypeAttrs as $attr) {
                        if ($model->getAttribute($attr) !== null) {
                            $alreadyLoaded = true;
                            break;
                        }
                    }
                }
                
                if (!$alreadyLoaded) {
                    $grouped[get_class($model)][] = $model;
                }
            }
        }

        if (empty($grouped)) {
            return $this;
        }

        foreach ($grouped as $class => $models) {
            /** @var SubtypeModel $first */
            $first = $models[0];
            $subtypeTable = $first->getSubtypeTable();
            $subtypeKeyName = $first->getSubtypeKeyName();
            $primaryKeyName = $first->getKeyName();

            // Collect keys for batch query
            $keys = array_filter(array_map(
                fn (SubtypeModel $m) => $m->getAttribute($primaryKeyName),
                $models
            ));

            if (empty($keys)) {
                continue;
            }

            // One query per subtype table
            $subdata = $first->getConnection()
                ->table($subtypeTable)
                ->whereIn($subtypeKeyName, $keys)
                ->get()
                ->keyBy($subtypeKeyName);

            // Fill each model with its subtype data
            foreach ($models as $model) {
                $extra = $subdata[$model->getKey()] ?? null;
                if ($extra) {
                    $model->forceFill((array) $extra);
                    $model->syncOriginal();
                }
            }
        }

        return $this;
    }
}
