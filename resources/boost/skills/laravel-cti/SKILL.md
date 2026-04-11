---
name: laravel-cti
description: Implement Class Table Inheritance (CTI) with Eloquent models using pannella/laravel-cti, including parent models, subtype models, relationships, and query building.
---

# Laravel CTI (Class Table Inheritance)

## When to use this skill

Use this skill when working with `pannella/laravel-cti` to implement the Class Table Inheritance pattern. Shared columns live in one parent table, type-specific columns live in their own tables, and a foreign key ties them together.

## Database Schema Pattern

CTI requires a parent table for shared attributes and one or more subtype tables for type-specific attributes. Type resolution uses either a lookup table or a direct discriminator column.

### With Lookup Table

```php
// Lookup table: type definitions
Schema::create('assessment_types', function (Blueprint $table) {
    $table->id();
    $table->string('label')->unique(); // 'quiz', 'survey', etc.
});

// Parent table: shared attributes
Schema::create('assessments', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->foreignId('type_id')->constrained('assessment_types');
    $table->timestamps();
});

// Subtype table: type-specific attributes (PK references parent)
Schema::create('assessment_quiz', function (Blueprint $table) {
    $table->unsignedBigInteger('assessment_id')->primary();
    $table->integer('passing_score')->nullable();
    $table->integer('time_limit')->nullable();
    $table->foreign('assessment_id')->references('id')->on('assessments')->onDelete('cascade');
});
```

### Without Lookup Table (Direct Discriminator)

The parent table stores the type label directly as a string column instead of a FK to a lookup table:

```php
Schema::create('assessments', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('type'); // stores 'quiz', 'survey', etc. directly
    $table->timestamps();
});

// Subtype tables are the same as above
```

**When to use which:** Use a lookup table if you want database-level FK constraints on valid type values. Use direct discriminator for a simpler schema with fewer tables. Direct discriminator also avoids the extra lookup query (though lookup table results are cached per-request).

## Configuring Models

There are two ways to configure CTI models: class properties or PHP 8.1 attributes.

### Option A: Class Properties

**Parent model** (with lookup table):

```php
use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Traits\HasSubtypes;

class Assessment extends Model
{
    use HasSubtypes;

    protected static $subtypeMap = [
        'quiz' => Quiz::class,
        'survey' => Survey::class,
    ];
    protected static $subtypeKey = 'type_id';
    protected static $subtypeLookupTable = 'assessment_types';
    protected static $subtypeLookupKey = 'id';
    protected static $subtypeLookupLabel = 'label';

    protected $fillable = ['title', 'type_id'];
}
```

**Parent model** (direct discriminator):

```php
class Assessment extends Model
{
    use HasSubtypes;

    protected static $subtypeMap = [
        'quiz' => Quiz::class,
        'survey' => Survey::class,
    ];
    protected static $subtypeKey = 'type'; // column contains the label directly
    // No $subtypeLookupTable, $subtypeLookupKey, or $subtypeLookupLabel needed

    protected $fillable = ['title', 'type'];
}
```

**Subtype model:**

```php
use Pannella\Cti\SubtypeModel;

class Quiz extends SubtypeModel
{
    protected $table = 'assessments';           // MUST be the parent table
    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeAttributes = ['passing_score', 'time_limit'];
    protected $ctiParentClass = Assessment::class;
    protected $subtypeKeyName = 'assessment_id'; // optional, defaults to parent PK name

    // Only subtype attrs needed; parent's $fillable is auto-inherited
    protected $fillable = ['passing_score', 'time_limit'];
}
```

### Option B: PHP 8.1 Attributes

**Parent model:**

```php
use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Attributes\SubtypeConfig;
use Pannella\Cti\Traits\HasSubtypes;

#[SubtypeConfig(
    map: ['quiz' => Quiz::class, 'survey' => Survey::class],
    key: 'type_id',
    lookupTable: 'assessment_types',
    lookupKey: 'id',
    lookupLabel: 'label',
)]
class Assessment extends Model
{
    use HasSubtypes;

    protected $fillable = ['title', 'type_id'];
}
```

**Subtype model:**

```php
use Pannella\Cti\Attributes\Subtype;
use Pannella\Cti\SubtypeModel;

#[Subtype(
    table: 'assessment_quiz',
    attributes: ['passing_score', 'time_limit'],
    parentClass: Assessment::class,
    keyName: 'assessment_id',
)]
class Quiz extends SubtypeModel
{
    protected $table = 'assessments'; // MUST be the parent table
    // Only subtype attrs needed; parent's $fillable is auto-inherited
    protected $fillable = ['passing_score', 'time_limit'];
}
```

When both a property and an attribute are defined, the property takes precedence.

## Critical Rules

- Subtype model `$table` MUST be set to the **parent** table name, not the subtype table.
- `$subtypeAttributes` MUST NOT overlap with parent table columns. Column names are the sole key used to route data between tables. Overlapping names cause silent data loss on save and corrupted reads on load. If both tables need a similar column, prefix the subtype column (e.g., `quiz_description` instead of `description`).
- `$fillable` and `$casts` are auto-inherited from the parent model. Subtypes only need to declare their own attributes. Set `$inheritParentFillable = false` to opt out. Use `$excludeParentFillable` to exclude specific parent attrs. `$guarded` is never merged.
- The discriminator column is auto-assigned on create. Do not set it manually.
- `save()` and `delete()` are wrapped in database transactions automatically.

## Querying

```php
// Subtype queries are auto-filtered by discriminator
$quizzes = Quiz::all();          // only quizzes
$surveys = Survey::all();        // only surveys

// Parent model returns all records morphed into correct subtype instances
$all = Assessment::all();        // Quiz and Survey instances

// Subtype columns auto-join (LEFT JOIN) when referenced in where/orderBy
$hard = Quiz::where('passing_score', '>', 90)->orderBy('time_limit')->get();

// Create
$quiz = Quiz::create(['title' => 'Final', 'passing_score' => 80]);

// Update
$quiz->time_limit = 60;
$quiz->save();

// Delete: removes subtype row first, then parent row
$quiz->delete();
```

### Mass Updates

Mass `update()`, `increment()`, and `decrement()` calls automatically split values between parent and subtype tables:

```php
// Updates title in parent table and passing_score in subtype table
Quiz::where('passing_score', '<', 60)->update([
    'title' => 'Remedial Quiz',
    'passing_score' => 60,
]);

// Increment subtype column with extra parent column update
Quiz::where('passing_score', '<', 100)->increment('passing_score', 5, [
    'title' => 'Updated Quiz',
]);
```

### Join Behavior

The query builder uses LEFT JOINs so parent records are not dropped if a subtype row is missing. The join is added once per query regardless of how many subtype columns you reference. If you only query parent columns, no join is added.

## Relationships

**Subtype relationships** (use subtype FK):

```php
class Quiz extends SubtypeModel
{
    public function questions()
    {
        return $this->subtypeHasMany(Question::class);
    }

    public function settings()
    {
        return $this->subtypeHasOne(QuizSettings::class);
    }

    public function instructor()
    {
        return $this->subtypeBelongsTo(Instructor::class);
    }

    public function tags()
    {
        return $this->subtypeBelongsToMany(Tag::class);
    }
}
```

The `subtype*` methods exist because the model's `$table` is the parent table, so standard Eloquent would derive foreign keys from the wrong table name. These methods use `$subtypeKeyName` instead. You can also use standard Eloquent methods with an explicit FK: `$this->hasMany(Question::class, 'assessment_id')`.

**Parent relationships** defined on the parent model are automatically accessible from subtype instances via `__call` proxy. No need to redefine them.

## Events

Subtype models fire `subtypeSaving`, `subtypeSaved`, `subtypeDeleting`, `subtypeDeleted` events. Returning `false` from `subtypeSaving` or `subtypeDeleting` halts the entire operation.

```php
Quiz::subtypeSaving(function (Quiz $quiz) {
    if ($quiz->passing_score > 100) {
        return false; // halts save
    }
});
```

Events can be mapped to event classes via `$dispatchesEvents`:

```php
protected $dispatchesEvents = [
    'subtypeSaving' => QuizSaving::class,
];
```

## Replicate and Refresh

`replicate()` copies both parent and subtype attributes, automatically excluding the subtype FK. `refresh()` reloads both parent and subtype data from the database.

## Configuration

Publish with `php artisan vendor:publish --tag=cti-config`. The `on_missing_subtype_data` option controls behavior when a subtype row is missing: `'log'` (default), `'exception'`, or `'null'`. Check programmatically with `$model->isSubtypeDataMissing()`.

## Caching

Lookup table results, discriminator type IDs, column validation, and parent property inheritance are all cached per-request. If you add type definitions at runtime (e.g., in a seeder), clear the caches:

```php
use Pannella\Cti\Support\SubtypeDiscriminatorScope;
use Pannella\Cti\Traits\BootsSubtypeModel;

SubtypeDiscriminatorScope::clearCache();
BootsSubtypeModel::clearTypeIdCache();
```

## Limitations

- `cursor()` and `lazy()` bypass batch loading. Call `$model->loadSubtypeData()` manually.
- Soft deletes only apply to the parent table; the subtype row remains. Restoring a soft-deleted parent works since the subtype row was never removed.
