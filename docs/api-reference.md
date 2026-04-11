# API Reference

## SubtypeModel

The abstract base class for all subtype models. Extends `Illuminate\Database\Eloquent\Model`.

### Configuration Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$subtypeTable` | `string\|null` | `null` | Table name for subtype-specific data |
| `$subtypeAttributes` | `array` | `[]` | Column names belonging to the subtype table |
| `$subtypeKeyName` | `string\|null` | `null` | Foreign key column in the subtype table. Defaults to the parent's primary key name |
| `$ctiParentClass` | `string\|null` | `null` | Fully-qualified class name of the parent CTI model |
| `$inheritParentFillable` | `bool` | `true` | Whether to merge the parent model's `$fillable` into this model |
| `$excludeParentFillable` | `array` | `[]` | Parent `$fillable` attributes to exclude from inheritance |

### Getter Methods

```php
// Returns the subtype table name
$quiz->getSubtypeTable(): ?string

// Returns the foreign key column name in the subtype table
$quiz->getSubtypeKeyName(): string

// Returns the list of subtype attribute names
$quiz->getSubtypeAttributes(): array

// Returns the type label for this model (e.g., 'quiz')
$quiz->getSubtypeLabel(): ?string

// Returns the parent CTI class name
$quiz->getCtiParentClass(): ?string

// Returns whether parent fillable inheritance is enabled
$quiz->getInheritParentFillable(): bool

// Returns the list of excluded parent fillable attributes
$quiz->getExcludeParentFillable(): array
```

### State Methods

```php
// Whether subtype data has been loaded (via batch loading or loadSubtypeData())
$quiz->isSubtypeDataLoaded(): bool

// Whether the subtype row was missing when data was loaded
$quiz->isSubtypeDataMissing(): bool

// Manually set the loaded flag (used internally by batch loading)
$quiz->setSubtypeDataLoaded(bool $loaded): void

// Manually load subtype data from the database
// Useful with cursor() and lazy() which bypass batch loading
$quiz->loadSubtypeData(): void
```

### Lifecycle Methods

```php
// Save parent and subtype data in a transaction
$quiz->save(array $options = []): bool

// Delete subtype row first, then parent row, in a transaction
$quiz->delete(): bool

// Copy both parent and subtype attributes; excludes the subtype FK
$copy = $quiz->replicate(?array $except = []): static

// Reload both parent and subtype data from the database
$quiz->refresh(): static
```

### Event Registration

```php
// Register a listener for before subtype data is saved
// Return false from the callback to halt the entire save
Quiz::subtypeSaving(callable $callback): void

// Register a listener for after subtype data is saved
Quiz::subtypeSaved(callable $callback): void

// Register a listener for before subtype data is deleted
// Return false from the callback to halt the entire delete
Quiz::subtypeDeleting(callable $callback): void

// Register a listener for after subtype data is deleted
Quiz::subtypeDeleted(callable $callback): void
```

### Static Methods

```php
// Handle a missing subtype row according to the configured strategy
// Called internally during batch loading and loadSubtypeData()
SubtypeModel::handleMissingSubtypeData(string $model, $key, SubtypeModel $instance): void
```

## HasSubtypes Trait

Applied to the parent model. Handles type resolution and morphing loaded instances into the correct subtype class.

### Configuration Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$subtypeMap` | `array` | `[]` | Maps type labels to subtype class names (e.g., `['quiz' => Quiz::class]`) |
| `$subtypeKey` | `string` | `''` | Discriminator column on the parent table |
| `$subtypeLookupTable` | `string\|null` | `null` | Lookup table name. Omit for direct discriminator mode |
| `$subtypeLookupKey` | `string\|null` | `null` | Primary key column in the lookup table |
| `$subtypeLookupLabel` | `string\|null` | `null` | Label column in the lookup table |

### Getter Methods

```php
// Returns the subtype map (label => class)
$assessment->getSubtypeMap(): array

// Returns the discriminator column name
$assessment->getSubtypeKey(): string

// Returns the lookup table name, or null in direct discriminator mode
$assessment->getSubtypeLookupTable(): ?string

// Returns the lookup table primary key column
$assessment->getSubtypeLookupKey(): ?string

// Returns the lookup table label column
$assessment->getSubtypeLookupLabel(): ?string

// Returns true if this model uses a lookup table, false for direct discriminator
$assessment->usesLookupTable(): bool

// Returns the type label for this instance
$assessment->getSubtypeLabel(): ?string
```

### Static Methods

```php
// Resolve a type ID (integer or string) to its label
// In direct mode, returns the value as-is
// In lookup table mode, queries the lookup table (cached after first call)
Assessment::resolveSubtypeLabel(int|string $typeId): ?string
```

## HasSubtypeRelations Trait

Included in `SubtypeModel`. Provides relationship methods that use the subtype's foreign key.

```php
// One-to-one; FK defaults to $subtypeKeyName
$this->subtypeHasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne

// One-to-many; FK defaults to $subtypeKeyName
$this->subtypeHasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany

// Inverse; FK defaults to $subtypeKeyName
$this->subtypeBelongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo

// Many-to-many; foreign pivot key defaults to $subtypeKeyName
$this->subtypeBelongsToMany(
    string $related,
    ?string $table = null,
    ?string $foreignPivotKey = null,
    ?string $relatedPivotKey = null,
    ?string $parentKey = null,
    ?string $relatedKey = null
): BelongsToMany
```

## SubtypeDiscriminatorScope

Global scope that filters subtype model queries by their discriminator value.

```php
// Clear the cached type IDs for all subtype models
// Useful in tests or after inserting new rows into the lookup table
SubtypeDiscriminatorScope::clearCache(): void
```

To remove the scope from a query:

```php
Quiz::withoutGlobalScope(SubtypeDiscriminatorScope::class)->get();
```

## BootsSubtypeModel Trait

Handles auto-assignment of the discriminator column on create and fillable/casts inheritance.

```php
// Clear the cached type IDs used during model creation
// Useful in tests or after modifying the lookup table
BootsSubtypeModel::clearTypeIdCache(): void
```

## SubtypedCollection

Returned by `newCollection()` on both parent and subtype models. Batch-loads subtype data on construction.

```php
// Manually trigger batch loading of subtype data
// Called automatically in the constructor; you'd only call this
// if you're building a collection manually
$collection->loadSubtypes(): static
```

## PHP 8.1 Attributes

### #[SubtypeConfig]

Applied to the parent model class. Alternative to static properties.

```php
#[SubtypeConfig(
    map: ['quiz' => Quiz::class],       // required
    key: 'type_id',                      // required
    lookupTable: 'assessment_types',     // optional
    lookupKey: 'id',                     // optional
    lookupLabel: 'label',                // optional
)]
```

### #[Subtype]

Applied to subtype model classes. Alternative to instance properties.

```php
#[Subtype(
    table: 'assessment_quiz',                                    // required
    attributes: ['passing_score', 'time_limit'],                 // required
    parentClass: Assessment::class,                              // required
    keyName: 'assessment_id',                                    // optional
    inheritParentFillable: true,                                 // optional
    excludeParentFillable: [],                                   // optional
)]
```

When both a class property and an attribute are defined, the **property takes precedence**.
