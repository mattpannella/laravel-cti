<?php

namespace Pannella\Cti\Traits;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasSubtypeRelations
{
    /**
     * Define a subtype-specific one-to-one relationship
     */
    protected function subtypeHasOne($related, $foreignKey = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);
        
        $foreignKey = $foreignKey ?: $this->getSubtypeKeyName();
        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasOne($instance->newQuery(), $this, 
            $instance->getTable() . '.' . $foreignKey, $localKey);
    }

    /**
     * Define a subtype-specific one-to-many relationship
     */
    protected function subtypeHasMany($related, $foreignKey = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);
        
        $foreignKey = $foreignKey ?: $this->getSubtypeKeyName();
        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasMany($instance->newQuery(), $this, 
            $instance->getTable() . '.' . $foreignKey, $localKey);
    }

    /**
     * Define a subtype-specific belongs-to relationship
     */
    protected function subtypeBelongsTo($related, $foreignKey = null, $ownerKey = null)
    {
        $instance = $this->newRelatedInstance($related);
        
        $foreignKey = $foreignKey ?: $this->getSubtypeKeyName();
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return $this->newBelongsTo($instance->newQuery(), $this, 
            $foreignKey, $ownerKey, null);
    }

    /**
     * Define a subtype-specific many-to-many relationship
     */
    protected function subtypeBelongsToMany($related, $table = null, $foreignPivotKey = null,
        $relatedPivotKey = null, $parentKey = null, $relatedKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        $table = $table ?: $this->joiningTable($related);
        $foreignPivotKey = $foreignPivotKey ?: $this->getSubtypeKeyName();
        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        return $this->newBelongsToMany(
            $instance->newQuery(), $this, $table,
            $foreignPivotKey, $relatedPivotKey, $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName()
        );
    }
}