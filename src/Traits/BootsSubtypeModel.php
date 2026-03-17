<?php

namespace Pannella\Cti\Traits;

use Pannella\Cti\Exceptions\SubtypeException;
use Pannella\Cti\SubtypeModel;
use Pannella\Cti\Support\SubtypeDiscriminatorScope;

trait BootsSubtypeModel
{
    /**
     * Cache of resolved type IDs per model class for the creating event.
     *
     * @var array<class-string, int|string>
     */
    protected static array $creatingTypeIdCache = [];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function bootBootsSubtypeModel()
    {
        // Add global scope to filter by discriminator
        static::addGlobalScope(new SubtypeDiscriminatorScope());

        static::creating(function (SubtypeModel $model) {
            $modelClass = get_class($model); // E.g., YourApp\Models\Quiz

            if (empty($model->ctiParentClass)) {
                return;
            }

            $ctiParentClass = $model->ctiParentClass;

            if (!class_exists($ctiParentClass)) {
                return;
            }

            try {
                $ctiParentClass = new $ctiParentClass();
                $discriminatorColumn = $ctiParentClass->getSubtypeKey();
                if (empty($model->getAttribute($discriminatorColumn))) {
                    // Use cached type ID if available
                    if (isset(static::$creatingTypeIdCache[$modelClass])) {
                        $model->setAttribute($discriminatorColumn, static::$creatingTypeIdCache[$modelClass]);
                        return;
                    }

                    $subtypeMap = $ctiParentClass->getSubtypeMap();
                    $label = array_search($modelClass, $subtypeMap, true);

                    if ($label !== false) {
                        $lookupTable = $ctiParentClass->getSubtypeLookupTable();
                        $lookupKeyCol = $ctiParentClass->getSubtypeLookupKey();
                        $lookupLabelCol = $ctiParentClass->getSubtypeLookupLabel();

                        $typeId = $model->getConnection()->table($lookupTable)
                            ->where($lookupLabelCol, $label)
                            ->value($lookupKeyCol);

                        if ($typeId !== null) {
                            static::$creatingTypeIdCache[$modelClass] = $typeId;
                            $model->setAttribute($discriminatorColumn, $typeId);
                        } else {
                            throw SubtypeException::typeResolutionFailed($label, $lookupTable);
                        }
                    }
                }
            } catch (SubtypeException $e) {
                throw $e;
            }
        });
    }

    /**
     * Clear the creating type ID cache.
     * Useful for testing or when type definitions change at runtime.
     *
     * @return void
     */
    public static function clearTypeIdCache(): void
    {
        static::$creatingTypeIdCache = [];
    }
}