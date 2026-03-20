# Laravel CTI

[![Tests](https://github.com/mattpannella/laravel-cti/actions/workflows/tests.yml/badge.svg)](https://github.com/mattpannella/laravel-cti/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/pannella/laravel-cti/v/stable)](https://packagist.org/packages/pannella/laravel-cti)
[![License](https://poser.pugx.org/pannella/laravel-cti/license)](https://packagist.org/packages/pannella/laravel-cti)

A Laravel package for implementing Class Table Inheritance pattern with Eloquent models. Unlike Laravel's polymorphic relations which denormalize data, CTI maintains proper database normalization by storing shared attributes in a parent table and subtype-specific attributes in separate tables.

## Features

- Automatic model type resolution and instantiation
- Seamless saving/updating across parent and subtype tables
- Automatic batch-loading of subtype data (no N+1 queries)
- Support for Eloquent events and relationships
- Full type safety and referential integrity

## Requirements

- PHP ^8.1
- Laravel 8.x – 13.x (`illuminate/database` >=8.0 <14.0)

## Installation

```bash
composer require pannella/laravel-cti
```

## Configuration

The package works out of the box with sensible defaults — no configuration required. If you want to customize behavior, publish the config file:

```bash
php artisan vendor:publish --tag=cti-config
```

This creates `config/cti.php` in your application:

```php
return [
    // 'exception', 'null', or 'log' (default)
    'on_missing_subtype_data' => 'log',
];
```

### Missing Subtype Data Handling

When a parent record exists but its corresponding subtype row is missing (a data integrity issue), the `on_missing_subtype_data` option controls the behavior:

| Value | Behavior |
|-------|----------|
| `'log'` *(default)* | Subtype attributes remain `null`, a warning is logged, and `$model->isSubtypeDataMissing()` returns `true` |
| `'exception'` | Throws `SubtypeException` immediately |
| `'null'` | Subtype attributes silently remain `null` — no warning, no flag |

You can check for missing data programmatically:

```php
$quiz = Quiz::find(1);

if ($quiz->isSubtypeDataMissing()) {
    // Handle the data integrity issue
}
```

## Quick Start

### Database Schema

CTI requires three types of tables: a **lookup table** for type definitions, a **parent table** for shared attributes, and one or more **subtype tables** for type-specific attributes.

```php
// Lookup table — stores the type definitions
Schema::create('assessment_types', function (Blueprint $table) {
    $table->id();
    $table->string('label')->unique(); // 'quiz', 'survey', etc.
});

// Parent table — shared attributes
Schema::create('assessments', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->foreignId('type_id')->constrained('assessment_types');
    $table->timestamps();
});

// Subtype table — quiz-specific attributes
// The primary key references the parent table's primary key
Schema::create('assessment_quiz', function (Blueprint $table) {
    $table->unsignedBigInteger('id')->primary();
    $table->integer('passing_score')->nullable();
    $table->integer('time_limit')->nullable();
    $table->boolean('show_correct_answers')->default(false);

    $table->foreign('id')->references('id')->on('assessments')->onDelete('cascade');
});

// Subtype table — survey-specific attributes
Schema::create('assessment_survey', function (Blueprint $table) {
    $table->unsignedBigInteger('id')->primary();
    $table->boolean('anonymous')->default(false);

    $table->foreign('id')->references('id')->on('assessments')->onDelete('cascade');
});
```

### Parent Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Traits\HasSubtypes;

class Assessment extends Model
{
    use HasSubtypes;

    // All properties are protected static
    protected static $subtypeMap = [
        'quiz' => Quiz::class,
        'survey' => Survey::class,
    ];

    protected static $subtypeKey = 'type_id';           // Discriminator column on the parent table
    protected static $subtypeLookupTable = 'assessment_types'; // Lookup table name
    protected static $subtypeLookupKey = 'id';           // Primary key in lookup table
    protected static $subtypeLookupLabel = 'label';      // Label column in lookup table

    protected $fillable = ['title', 'type_id'];
}
```

| Property | Description |
|----------|-------------|
| `$subtypeMap` | Maps type labels (from the lookup table) to subtype class names |
| `$subtypeKey` | Column on the parent table that references the lookup table |
| `$subtypeLookupTable` | Table containing type definitions |
| `$subtypeLookupKey` | Primary key column in the lookup table |
| `$subtypeLookupLabel` | Label column in the lookup table (values must match `$subtypeMap` keys) |

### Subtype Model

```php
namespace App\Models;

use Pannella\Cti\SubtypeModel;

class Quiz extends SubtypeModel
{
    // IMPORTANT: $table must be set to the parent table name
    protected $table = 'assessments';

    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeAttributes = [
        'passing_score',
        'time_limit',
        'show_correct_answers',
    ];

    protected $ctiParentClass = Assessment::class;

    // Only subtype attributes needed — parent's $fillable is auto-inherited
    protected $fillable = [
        'passing_score',
        'time_limit',
        'show_correct_answers',
    ];
}
```

| Property | Description |
|----------|-------------|
| `$table` | **Must** be set to the parent table name (e.g. `assessments`) |
| `$subtypeTable` | Table containing this subtype's specific fields |
| `$subtypeAttributes` | Array of column names that belong to the subtype table. **Must not overlap with parent table columns.** |
| `$ctiParentClass` | Fully-qualified class name of the parent model |
| `$subtypeKeyName` | *(Optional)* Foreign key column in the subtype table. Defaults to the parent model's primary key name (`id`) |
| `$fillable` | Only subtype attributes needed — parent attributes are auto-inherited (see below) |
| `$inheritParentFillable` | *(Optional)* Set to `false` to disable automatic `$fillable` inheritance from parent. Default: `true` |
| `$excludeParentFillable` | *(Optional)* Array of parent `$fillable` attributes to exclude from inheritance |

#### Automatic `$fillable` and `$casts` Inheritance

Subtype models automatically inherit `$fillable` and `$casts` from their CTI parent model. You only need to declare subtype-specific attributes:

```php
class Quiz extends SubtypeModel
{
    protected $table = 'assessments';
    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeAttributes = ['passing_score', 'time_limit', 'show_correct_answers'];
    protected $ctiParentClass = Assessment::class;

    // Only subtype attributes needed — parent's $fillable is auto-inherited
    protected $fillable = [
        'passing_score',
        'time_limit',
        'show_correct_answers',
    ];

    // Only subtype casts needed — parent's $casts is auto-inherited
    protected $casts = [
        'passing_score' => 'integer',
        'show_correct_answers' => 'boolean',
    ];
}
```

**Precedence:**
- `$casts`: Parent casts are merged first, then subtype casts overlay — subtype wins on conflicts.
- `$fillable`: Parent and subtype arrays are merged and deduplicated. Existing subtypes that already list parent attributes continue to work (duplicates are removed).

**Opt-out:** Set `$inheritParentFillable = false` to disable fillable inheritance entirely. Use `$excludeParentFillable` to exclude specific parent attributes. Both options work as class properties or via the `#[Subtype]` attribute:

```php
// Via class properties
class Quiz extends SubtypeModel
{
    protected bool $inheritParentFillable = false; // don't inherit any parent fillable

    // Or selectively exclude:
    // protected array $excludeParentFillable = ['description'];
}

// Via attribute
#[Subtype(
    table: 'assessment_quiz',
    attributes: ['passing_score', 'time_limit'],
    parentClass: Assessment::class,
    inheritParentFillable: false,
    // excludeParentFillable: ['description'],
)]
class Quiz extends SubtypeModel { /* ... */ }
```

> **Note:** `$guarded` is never merged — it's a security boundary and must be set explicitly on each model.

The discriminator column (`type_id`) is **auto-assigned on create** — you don't need to set it manually. The `BootsSubtypeModel` trait looks up the correct value from the lookup table based on the `$subtypeMap`. If the discriminator is already set, it won't be overridden.

### PHP 8.1 Attribute-Based Configuration

As an alternative to class properties, you can configure CTI models using PHP 8.1 attributes. This keeps all configuration in a single, declarative annotation at the top of your class.

**Parent model with `#[SubtypeConfig]`:**

```php
namespace App\Models;

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

**Subtype model with `#[Subtype]`:**

```php
namespace App\Models;

use Pannella\Cti\Attributes\Subtype;
use Pannella\Cti\SubtypeModel;

#[Subtype(
    table: 'assessment_quiz',
    attributes: ['passing_score', 'time_limit', 'show_correct_answers'],
    parentClass: Assessment::class,
    keyName: 'assessment_id',
)]
class Quiz extends SubtypeModel
{
    // $table must still be set to the parent table name
    protected $table = 'assessments';

    // Only subtype attributes needed — parent's $fillable is auto-inherited
    protected $fillable = [
        'passing_score',
        'time_limit',
        'show_correct_answers',
    ];
}
```

| Attribute | Target | Parameters |
|-----------|--------|------------|
| `#[SubtypeConfig]` | Parent model | `map`, `key`, `lookupTable`, `lookupKey`, `lookupLabel` |
| `#[Subtype]` | Subtype model | `table`, `attributes`, `parentClass`, `keyName` (optional), `inheritParentFillable` (optional), `excludeParentFillable` (optional) |

**Precedence:** When both a class property and an attribute are defined, the **property takes precedence**. This lets you use attributes as defaults and override individual values with properties when needed.

**Performance:** Attribute resolution uses reflection, but results are cached per-class for the lifetime of the request. There is no performance difference after the first access.

### Using the Models

Subtype data is loaded automatically whenever models are fetched via `get()`, `paginate()`, `find()`, `all()`, etc.

**Important:** Subtype models automatically filter queries by their discriminator value. For example, `Quiz::all()` only returns records where `type_id` matches the quiz type — it will not return surveys. The parent model (`Assessment::all()`) returns all records and morphs them into the correct subtype instances.

```php
// Querying subtype models only returns records of that type
$quizzes = Quiz::all();     // Only quizzes (type_id = 1)
$surveys = Survey::all();   // Only surveys (type_id = 2)

// Parent model returns ALL records, morphed into correct subtype instances
$assessments = Assessment::all();  // Returns Quiz and Survey instances

// Each model is an instance of the correct subtype class
$assessments->first() instanceof Quiz; // true or false depending on type_id

// You can remove the discriminator filter if needed
$allRecords = Quiz::withoutGlobalScope(\Pannella\Cti\Support\SubtypeDiscriminatorScope::class)->get();

// Pagination works seamlessly — subtype data is batch-loaded for the page
$quizzes = Quiz::paginate(15);  // Only quizzes

// Create new subtype instance
$quiz = new Quiz();
$quiz->title = 'Final Exam';        // parent attribute
$quiz->passing_score = 80;          // subtype attribute
$quiz->save();                      // saves to both tables in a transaction

// Or use mass assignment
$quiz = Quiz::create([
    'title' => 'Final Exam',
    'passing_score' => 80,
    'time_limit' => 60,
]);

// Load single instance
$quiz = Quiz::find(1);              // hydrates both parent and subtype data

// Update existing
$quiz->time_limit = 60;
$quiz->save();                      // updates only modified tables

// Delete — removes subtype row first, then parent row
$quiz->delete();
```

## Relationships

### Subtype Relationships

Define relationships that use the subtype's foreign key with the convenience methods provided by the `HasSubtypeRelations` trait (included in `SubtypeModel`):

```php
class Quiz extends SubtypeModel
{
    protected $table = 'assessments';
    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeAttributes = ['passing_score', 'time_limit', 'show_correct_answers'];
    protected $ctiParentClass = Assessment::class;

    public function questions(): HasMany
    {
        // FK defaults to $subtypeKeyName (the subtype table's FK column)
        return $this->subtypeHasMany(Question::class);
    }

    public function gradingRubric(): HasOne
    {
        return $this->subtypeHasOne(GradingRubric::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->subtypeBelongsTo(Instructor::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->subtypeBelongsToMany(Tag::class);
    }
}
```

Available methods:

| Method | Description |
|--------|-------------|
| `subtypeHasOne($related, $foreignKey?, $localKey?)` | One-to-one from subtype |
| `subtypeHasMany($related, $foreignKey?, $localKey?)` | One-to-many from subtype |
| `subtypeBelongsTo($related, $foreignKey?, $ownerKey?)` | Inverse one-to-one/many |
| `subtypeBelongsToMany($related, $table?, $foreignPivotKey?, $relatedPivotKey?, $parentKey?, $relatedKey?)` | Many-to-many from subtype |

All of these default the foreign key to `$subtypeKeyName` (which itself defaults to the parent model's primary key name).

### Parent Relationship Inheritance

Relationships defined on the parent model are automatically accessible from subtype instances via the `__call` proxy:

```php
// Defined on Assessment (parent)
class Assessment extends Model
{
    use HasSubtypes;
    // ...

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}

// Accessible from Quiz (subtype) without redefining it
$quiz = Quiz::find(1);
$quiz->tags; // works — proxied to Assessment::tags()
```

### Standard Eloquent Alternative

You can also use standard Eloquent relationship methods with an explicit foreign key instead of the `subtype*` convenience methods:

```php
public function questions(): HasMany
{
    return $this->hasMany(Question::class, 'assessment_id');
}
```

## Query Builder

The `SubtypeQueryBuilder` automatically joins the subtype table whenever a subtype column is referenced in a query. No manual joins needed.

```php
// Auto-joins assessment_quiz table because passing_score is a subtype attribute
$quizzes = Quiz::where('passing_score', '>', 70)->get();

// Chain multiple conditions across both tables
$quizzes = Quiz::where('passing_score', '>', 70)
    ->where('title', 'like', '%Final%')   // parent column — no join needed
    ->orderBy('time_limit', 'desc')        // subtype column — join added once
    ->get();

// Aggregates work too
$avg = Quiz::avg('passing_score');
```

### Supported Methods

The following query builder methods support automatic subtype joins:

| Method | Example |
|--------|---------|
| `where` / `orWhere` | `Quiz::where('passing_score', '>', 70)` |
| `whereIn` / `orWhereIn` | `Quiz::whereIn('passing_score', [70, 80, 90])` |
| `whereNotIn` / `orWhereNotIn` | `Quiz::whereNotIn('time_limit', [30, 60])` |
| `whereNull` / `orWhereNull` | `Quiz::whereNull('time_limit')` |
| `whereNotNull` / `orWhereNotNull` | `Quiz::whereNotNull('passing_score')` |
| `whereColumn` / `orWhereColumn` | `Quiz::whereColumn('passing_score', '>', 'time_limit')` |
| `whereBetween` / `orWhereBetween` | `Quiz::whereBetween('passing_score', [60, 100])` |
| `whereNotBetween` / `orWhereNotBetween` | `Quiz::whereNotBetween('passing_score', [0, 50])` |
| `whereDate` / `whereYear` / `whereMonth` / `whereDay` | `Quiz::whereDate('created_at', '2024-01-01')` |
| `orderBy` / `orderByDesc` | `Quiz::orderBy('time_limit')` |
| `latest` / `oldest` | `Quiz::latest('time_limit')` |
| `groupBy` | `Quiz::groupBy('passing_score')` |
| `having` | `Quiz::groupBy('passing_score')->having('passing_score', '>', 70)` |
| `select` / `addSelect` | `Quiz::select('title', 'passing_score')` |
| `pluck` / `value` | `Quiz::pluck('passing_score')` |
| `update` (mass) | `Quiz::where('passing_score', '<', 60)->update(['passing_score' => 60])` |
| `increment` / `decrement` | `Quiz::where('passing_score', '<', 100)->increment('passing_score', 5)` |
| Aggregates | `Quiz::avg('passing_score')`, `Quiz::sum('time_limit')`, etc. |

## Events

Subtype models fire additional events around subtype data persistence:

| Event | Fires | Halts on `false`? |
|-------|-------|-------------------|
| `subtypeSaving` | Before subtype data is saved | Yes |
| `subtypeSaved` | After subtype data is saved | No |
| `subtypeDeleting` | Before subtype data is deleted | Yes |
| `subtypeDeleted` | After subtype data is deleted | No |

Register listeners using the static methods:

```php
Quiz::subtypeSaving(function (Quiz $quiz) {
    // Validate or modify subtype data before save
    if ($quiz->passing_score > 100) {
        return false; // halts the save
    }
});

Quiz::subtypeSaved(function (Quiz $quiz) {
    // React after subtype data is saved
});

Quiz::subtypeDeleting(function (Quiz $quiz) {
    // Clean up before subtype data is deleted
});

Quiz::subtypeDeleted(function (Quiz $quiz) {
    // React after subtype data is deleted
});
```

Returning `false` from `subtypeSaving` or `subtypeDeleting` will halt the entire operation (including the parent table write).

You can also map these events to event classes via the `$dispatchesEvents` property:

```php
protected $dispatchesEvents = [
    'subtypeSaving' => QuizSaving::class,
];
```

## How It Works

### Type Resolution

When a parent model is loaded from the database, the `HasSubtypes` trait's `newFromBuilder()` method reads the discriminator column (`type_id`), looks up the corresponding label from the lookup table, and maps it to a subtype class via `$subtypeMap`. The returned model is an instance of the subtype class with all parent attributes set.

### Batch Loading

Both the parent model (via `HasSubtypes`) and subtype models (via `SubtypeModel`) return a `SubtypedCollection` from `newCollection()`. The collection's constructor groups models by subtype class and executes **one query per subtype table** to load all subtype data, eliminating N+1 queries.

### Cast and Fillable Inheritance

Parent model `$casts` and `$fillable` are automatically merged into subtype instances during construction. This means fresh instances (`new Quiz()`) and DB-loaded instances both have the correct casts and fillable attributes. Subtype values take precedence on conflicts.

### Column Overlap Validation

`$subtypeAttributes` must not contain any column names that also exist on the parent table. The package validates this automatically on the first `save()` or `loadSubtypeData()` call for each model class (one schema query per class, cached for the lifetime of the request). If an overlap is detected, a `SubtypeException` is thrown immediately with a clear message listing the conflicting columns.

**Why this restriction exists:** Internally, column names are the sole key used to route data between parent and subtype tables. Every operation — save, load, query — splits attributes by checking whether a column is in `$subtypeAttributes`. If the same column name exists in both tables:

- **Saves** would send the value only to the subtype table, leaving the parent column NULL or stale
- **Loads** would overwrite the parent's value with the subtype's value (which may be NULL) via `forceFill()`
- **Queries** would produce ambiguous SQL column references after the auto-join

This is a fundamental constraint of Eloquent's flat `$attributes` array — there's no way to store two values for the same key.

**Workaround:** Rename the subtype column with a prefix. For example, if both your parent table and subtype table have a `description` column, rename the subtype column to `quiz_description`:

```php
// Migration
Schema::table('assessment_quiz', function (Blueprint $table) {
    $table->renameColumn('description', 'quiz_description');
});

// Model
protected $subtypeAttributes = ['quiz_description', 'passing_score', 'time_limit'];
```

### Transactions

`save()` wraps both parent and subtype table writes in a database transaction. If either write fails, the entire operation is rolled back.

### Replicate and Refresh

- `replicate()` copies both parent and subtype attributes, automatically excluding the subtype foreign key so a new record can be created.
- `refresh()` reloads both parent attributes and subtype data from the database.

### Serialization

`toArray()` and `toJson()` include both parent and subtype attributes, so API responses and JSON serialization work as expected without extra configuration.

## Known Limitations

### `cursor()` and `lazy()` Bypass Batch Loading

`cursor()` and `lazy()` iterate models individually and bypass `newCollection()`, so subtype data is **not** automatically loaded. Call `loadSubtypeData()` manually:

```php
Quiz::cursor()->each(function (Quiz $quiz) {
    $quiz->loadSubtypeData();
    // now $quiz->passing_score is available
});
```

### Soft Deletes

Soft deletes are not handled on subtype tables. The parent table can use `SoftDeletes` normally, but the subtype row will remain even when the parent is soft-deleted.

## License

MIT
