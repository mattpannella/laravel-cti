# Laravel CTI

[![Tests](https://github.com/mattpannella/laravel-cti/actions/workflows/tests.yml/badge.svg)](https://github.com/mattpannella/laravel-cti/actions/workflows/tests.yml)

A Laravel package for implementing Class Table Inheritance pattern with Eloquent models. Unlike Laravel's polymorphic relations which denormalize data, CTI maintains proper database normalization by storing shared attributes in a parent table and subtype-specific attributes in separate tables.

## Features

- Automatic model type resolution and instantiation
- Seamless saving/updating across parent and subtype tables
- Automatic batch-loading of subtype data (no N+1 queries)
- Support for Eloquent events and relationships
- Full type safety and referential integrity

## Requirements

- PHP ^8.0
- Laravel 8.x – 12.x (`illuminate/database` >=8.0 <13.0)

## Installation

```bash
composer require pannella/laravel-cti
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

    // $fillable should include BOTH parent and subtype attributes
    protected $fillable = [
        'title',           // parent attribute
        'passing_score',   // subtype attribute
        'time_limit',
        'show_correct_answers',
    ];
}
```

| Property | Description |
|----------|-------------|
| `$table` | **Must** be set to the parent table name (e.g. `assessments`) |
| `$subtypeTable` | Table containing this subtype's specific fields |
| `$subtypeAttributes` | Array of column names that belong to the subtype table |
| `$ctiParentClass` | Fully-qualified class name of the parent model |
| `$subtypeKeyName` | *(Optional)* Foreign key column in the subtype table. Defaults to the parent model's primary key name (`id`) |
| `$fillable` | Should include both parent and subtype attributes |

The discriminator column (`type_id`) is **auto-assigned on create** — you don't need to set it manually. The `BootsSubtypeModel` trait looks up the correct value from the lookup table based on the `$subtypeMap`. If the discriminator is already set, it won't be overridden.

### Using the Models

Subtype data is loaded automatically whenever models are fetched via `get()`, `paginate()`, `find()`, `all()`, etc.

```php
// Fetch with automatic subtype resolution and batch-loaded subtype data
$assessments = Assessment::all();

// Each model is an instance of the correct subtype class
$assessments->first() instanceof Quiz; // true

// Pagination works seamlessly — subtype data is batch-loaded for the page
$assessments = Assessment::paginate(15);

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
| `where` | `Quiz::where('passing_score', '>', 70)` |
| `whereIn` | `Quiz::whereIn('passing_score', [70, 80, 90])` |
| `whereNotIn` | `Quiz::whereNotIn('time_limit', [30, 60])` |
| `whereNull` | `Quiz::whereNull('time_limit')` |
| `whereNotNull` | `Quiz::whereNotNull('passing_score')` |
| `whereColumn` | `Quiz::whereColumn('passing_score', '>', 'time_limit')` |
| `whereBetween` | `Quiz::whereBetween('passing_score', [60, 100])` |
| `orderBy` | `Quiz::orderBy('time_limit')` |
| `groupBy` | `Quiz::groupBy('passing_score')` |
| `having` | `Quiz::groupBy('passing_score')->having('passing_score', '>', 70)` |
| `select` | `Quiz::select('title', 'passing_score')` |
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

### Cast Inheritance

Parent model casts are automatically merged into subtype instances. If `Assessment` defines `'type_id' => 'integer'`, that cast is applied when a `Quiz` is instantiated.

### Transactions

`save()` wraps both parent and subtype table writes in a database transaction. If either write fails, the entire operation is rolled back.

### Replicate and Refresh

- `replicate()` copies both parent and subtype attributes, automatically excluding the subtype foreign key so a new record can be created.
- `refresh()` reloads both parent attributes and subtype data from the database.

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

### Batch Loader Heuristic

The batch loader checks if subtype data is already loaded by testing whether any subtype attribute is not `null`. If all subtype attributes for a given record are legitimately `null`, a redundant query may fire to re-load the data.

## License

MIT
