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
            // Load subtype data for each group of models
            foreach ($models as $model) {
                if (method_exists($model, 'loadSubtypeData')) {
                    $model->loadSubtypeData();
                }
            }
        }

        return $this;
    }
}
