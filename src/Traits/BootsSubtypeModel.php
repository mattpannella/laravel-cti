<?php

namespace Pannella\Cti\Traits;

use Pannella\Cti\Exceptions\SubtypeException;
use Pannella\Cti\SubtypeModel;

trait BootsSubtypeModel
{
    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function bootBootsSubtypeModel()
    {
        static::creating(function (SubtypeModel $model) {
            $modelClass = get_class($model); // E.g., YourApp\Models\Quiz

            if (!property_exists($model, 'ctiParentClass') || empty($model->ctiParentClass)) {
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
                    $subtypeMap = $ctiParentClass->getSubtypeMap();
                    $label = array_search($modelClass, $subtypeMap, true);

                    if ($label !== false) {
                        $lookupTable = $ctiParentClass->getSubtypeLookupTable();
                        $lookupKeyCol = $ctiParentClass->getSubtypeLookupKey();
                        $lookupLabelCol = $ctiParentClass->getSubtypeLookupLabel();

                        // The null check for these was implicitly handled by the accessor methods throwing an exception.
                        // If they could return null, additional checks would be needed here.
                        
                        $typeId = $model->getConnection()->table($lookupTable)
                                        ->where($lookupLabelCol, $label)
                                        ->value($lookupKeyCol);

                        if ($typeId !== null) {
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
}