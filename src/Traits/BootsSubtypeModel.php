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
     * Cache of parent model properties (fillable, casts) per subtype class.
     *
     * @var array<class-string, array{fillable: array<int, string>, casts: array<string, mixed>}>
     */
    protected static array $parentPropertyCache = [];

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

            $ctiParentClass = $model->getCtiParentClass();
            if (empty($ctiParentClass)) {
                return;
            }

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
     * Initialize the trait on each model instance.
     * Merges parent model's $fillable and $casts into the subtype model.
     *
     * @return void
     */
    public function initializeBootsSubtypeModel(): void
    {
        $parentClass = $this->getCtiParentClass();
        if (empty($parentClass) || !class_exists($parentClass)) {
            return;
        }

        if (!isset(static::$parentPropertyCache[static::class])) {
            $parent = new $parentClass();
            static::$parentPropertyCache[static::class] = [
                'fillable' => $parent->getFillable(),
                'casts' => $parent->getCasts(),
            ];
        }

        $cached = static::$parentPropertyCache[static::class];

        // Merge casts: subtype wins on conflict
        $this->casts = array_merge($cached['casts'], $this->casts);

        // Merge fillable (opt-out via $inheritParentFillable = false)
        if ($this->getInheritParentFillable()) {
            $parentFillable = array_diff($cached['fillable'], $this->getExcludeParentFillable());
            $this->fillable = array_values(array_unique(
                array_merge($parentFillable, $this->fillable)
            ));
        }
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
        static::$parentPropertyCache = [];
    }
}