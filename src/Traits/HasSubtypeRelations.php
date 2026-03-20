<?php

namespace Pannella\Cti\Traits;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Trait that provides relationship methods for subtype models.
 *
 * This trait adds methods for defining relationships specific to subtype models,
 * ensuring proper foreign key handling and table joins for CTI relationships.
 */
trait HasSubtypeRelations
{
    /**
     * Define a one-to-one relationship from the subtype table.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @param class-string<TRelatedModel> $related Related model class name
     * @param string|null $foreignKey Foreign key column on related table
     * @param string|null $localKey Local key column on subtype table
     * @return HasOne<TRelatedModel, $this>
     */
    protected function subtypeHasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        /** @var TRelatedModel $instance */
        $instance = new $related();

        $foreignKey = $foreignKey ?: $this->getSubtypeKeyName();
        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasOne($instance->newQuery(), $this,
            $instance->getTable() . '.' . $foreignKey, $localKey);
    }

    /**
     * Define a one-to-many relationship from the subtype table.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @param class-string<TRelatedModel> $related Related model class name
     * @param string|null $foreignKey Foreign key column on related table
     * @param string|null $localKey Local key column on subtype table
     * @return HasMany<TRelatedModel, $this>
     */
    protected function subtypeHasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        /** @var TRelatedModel $instance */
        $instance = new $related();

        $foreignKey = $foreignKey ?: $this->getSubtypeKeyName();
        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasMany($instance->newQuery(), $this,
            $instance->getTable() . '.' . $foreignKey, $localKey);
    }

    /**
     * Define a belongs-to relationship from the subtype table.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @param class-string<TRelatedModel> $related Related model class name
     * @param string|null $foreignKey Foreign key column on subtype table
     * @param string|null $ownerKey Owner key column on related table
     * @return BelongsTo<TRelatedModel, $this>
     */
    protected function subtypeBelongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        /** @var TRelatedModel $instance */
        $instance = new $related();

        $foreignKey = $foreignKey ?: $this->getSubtypeKeyName();
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return $this->newBelongsTo($instance->newQuery(), $this,
            $foreignKey, $ownerKey, $foreignKey);
    }

    /**
     * Define a many-to-many relationship from the subtype table.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @param class-string<TRelatedModel> $related Related model class name
     * @param string|null $table Pivot table name
     * @param string|null $foreignPivotKey Foreign key for subtype on pivot table
     * @param string|null $relatedPivotKey Related model key on pivot table
     * @param string|null $parentKey Local key on subtype table
     * @param string|null $relatedKey Local key on related table
     * @return BelongsToMany<TRelatedModel, $this>
     */
    protected function subtypeBelongsToMany(string $related, ?string $table = null,
                                            ?string $foreignPivotKey = null, ?string $relatedPivotKey = null,
                                            ?string $parentKey = null, ?string $relatedKey = null): BelongsToMany
    {
        /** @var TRelatedModel $instance */
        $instance = new $related();

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