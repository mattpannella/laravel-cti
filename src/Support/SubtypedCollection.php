<?php
namespace Pannella\Cti\Support;

use Illuminate\Database\Eloquent\Collection;

class SubtypedCollection extends Collection
{
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

            /** @var \Pannella\Cti\SubtypeModel $subInstance */
            $subInstance = new $subclass;
            $baseTable = $subInstance->getTable(); // e.g. "assessments"
            $keyName = $subInstance->getSubtypeKeyName();

            // Use subtypeTable if it exists (e.g., "assessment_quiz")
            $subtypeTable = $subInstance->getSubtypeTable();

            if (!$subtypeTable) {
                continue;
            }

            // Collect model IDs
            $ids = $models->pluck($subInstance->getKeyName())->all();

            // Fetch subtype rows in bulk
            $subdata = $subInstance->getConnection()
                ->table($subtypeTable)
                ->whereIn($keyName, $ids)
                ->get()
                ->keyBy($keyName);
            // Replace each model with hydrated subtype
            foreach ($models as $model) {
                $sub = (new $subclass)->newInstance([], true);
                $sub->setRawAttributes($model->getAttributes(), true);
                $sub->exists = true;

                // Preserve loaded relationships
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
