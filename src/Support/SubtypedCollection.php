<?php

namespace Pannella\Cti\Support;

use Illuminate\Database\Eloquent\Collection;

/**
 * Collection class with support for loading subtype data.
 * 
 * Extends Laravel's Collection to add functionality for loading
 * subtype-specific data for multiple models efficiently.
 *
 * @template TKey of array-key
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends \Illuminate\Database\Eloquent\Collection<TKey, TModel>
 */
class SubtypedCollection extends Collection
{
    /**
     * Load subtype data for all models in the collection.
     * 
     * Groups models by subtype and loads their subtype-specific data
     * efficiently using as few queries as possible.
     *
     * @return \Pannella\Cti\Support\SubtypedCollection<TKey, TModel>
     */
    
    public function loadSubtypes(): static
    {
        if ($this->isEmpty()) {
            return $this;
        }

        // Group models by subtype label
        $grouped = $this->groupBy(function ($model) {
            return method_exists($model, 'getSubtypeLabel') ? $model->getSubtypeLabel() : null;
        });

        foreach ($grouped as $label => $models) {
            $first = $models->first();

            if (!method_exists($first, 'getSubtypeMap')) {
                continue;
            }

            $map = $first->getSubtypeMap();
            $subclass = $map[$label] ?? null;

            if (!$subclass) {
                continue;
            }

            /** \Pannella\Cti\SubtypeModel $subInstance */
            $subInstance = new $subclass;
            $baseTable = $subInstance->getTable(); // e.g. "assessments"
            $keyName = $subInstance->getSubtypeKeyName();

            // Use subtypeTable if it exists (e.g., "assessment_quiz")
            $subtypeTable = $subInstance->getSubtypeTable();

            if (!$subtypeTable) {
                continue;
            }

            //collect model IDs
            $ids = $models->pluck($subInstance->getKeyName())->all();

            //fetch subtype rows in bulk
            $subdata = $subInstance->getConnection()
                ->table($subtypeTable)
                ->whereIn($keyName, $ids)
                ->get()
                ->keyBy($keyName);
            //replace each model with hydrated subtype
            foreach ($models as $model) {
                $sub = (new $subclass)->newInstance([], true);
                $sub->setRawAttributes($model->getAttributes(), true);
                $sub->exists = true;

                //preserve loaded relationships
                $sub->setRelations($model->getRelations());

                $extra = $subdata[$model->getKey()] ?? null;
                if ($extra) {
                    $sub->fill((array) $extra);
                }

                $index = $this->search($model, true);
                if ($index !== false) {
                    $this->items[$index] = $sub;
                }
            }
        }

        return $this;
    }
}