---
name: laravel-cti
description: Implement Class Table Inheritance (CTI) with Eloquent models using pannella/laravel-cti, including parent models, subtype models, relationships, and query building.
---

# Laravel CTI (Class Table Inheritance)

## When to use this skill

Use this skill when working with `pannella/laravel-cti` to implement the Class Table Inheritance pattern — storing shared attributes in a parent table and subtype-specific attributes in separate tables while maintaining proper database normalization.

## Database Schema Pattern

CTI requires three types of tables:

```php
// 1. Lookup table — type definitions
Schema::create('assessment_types', function (Blueprint $table) {
    $table->id();
    $table->string('label')->unique(); // 'quiz', 'survey', etc.
});

// 2. Parent table — shared attributes
Schema::create('assessments', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->foreignId('type_id')->constrained('assessment_types');
    $table->timestamps();
});

// 3. Subtype table — type-specific attributes (PK references parent)
Schema::create('assessment_quiz', function (Blueprint $table) {
    $table->unsignedBigInteger('id')->primary();
    $table->integer('passing_score')->nullable();
    $table->integer('time_limit')->nullable();
    $table->foreign('id')->references('id')->on('assessments')->onDelete('cascade');
});
```

## Configuring Models

There are two ways to configure CTI models: class properties or PHP 8.1 attributes.

### Option A: Class Properties

**Parent model** — uses `HasSubtypes` trait with static properties:

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
}
```

**Subtype model** — extends `SubtypeModel` with instance properties:

```php
use Pannella\Cti\SubtypeModel;

class Quiz extends SubtypeModel
{
    protected $table = 'assessments';           // MUST be the parent table
    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeAttributes = ['passing_score', 'time_limit'];
    protected $ctiParentClass = Assessment::class;
    protected $subtypeKeyName = 'assessment_id'; // optional, defaults to parent PK

    // Only subtype attrs needed — parent's $fillable is auto-inherited
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
    // Only subtype attrs needed — parent's $fillable is auto-inherited
    protected $fillable = ['passing_score', 'time_limit'];
}
```

When both a property and an attribute are defined, the property takes precedence.

## Critical Rules

- Subtype model `$table` MUST be set to the **parent** table name, not the subtype table.
- `$subtypeAttributes` MUST NOT overlap with parent table columns. Column names are the sole key used to route data between tables — overlapping names cause silent data loss on save and corrupted reads on load. If both tables need a similar column, prefix the subtype column (e.g., `quiz_description` instead of `description`).
- `$fillable` and `$casts` are auto-inherited from the parent model. Subtypes only need to declare their own attributes. Set `$inheritParentFillable = false` (property or `#[Subtype]` attribute) to opt out of fillable inheritance. Use `$excludeParentFillable` to exclude specific parent attrs. `$guarded` is never merged.
- The discriminator column (`type_id`) is auto-assigned on create — do not set it manually.
- `save()` and `delete()` are wrapped in database transactions automatically.

## Querying

```php
// Subtype queries are auto-filtered by discriminator
$quizzes = Quiz::all();          // only quizzes
$surveys = Survey::all();        // only surveys

// Parent model returns all records morphed into correct subtype instances
$all = Assessment::all();        // Quiz and Survey instances

// Subtype columns auto-join when referenced in where/orderBy
$hard = Quiz::where('passing_score', '>', 90)->orderBy('time_limit')->get();

// Create
$quiz = Quiz::create(['title' => 'Final', 'passing_score' => 80]);

// Update
$quiz->time_limit = 60;
$quiz->save();

// Delete — removes subtype row first, then parent row
$quiz->delete();
```

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

**Parent relationships** defined on the parent model are automatically accessible from subtype instances — no need to redefine them.

## Events

Subtype models fire `subtypeSaving`, `subtypeSaved`, `subtypeDeleting`, `subtypeDeleted` events. Returning `false` from `subtypeSaving` or `subtypeDeleting` halts the entire operation.

```php
Quiz::subtypeSaving(function (Quiz $quiz) {
    if ($quiz->passing_score > 100) {
        return false; // halts save
    }
});
```

## Configuration

Publish with `php artisan vendor:publish --tag=cti-config`. The `on_missing_subtype_data` option controls behavior when a subtype row is missing: `'log'` (default), `'exception'`, or `'null'`.

## Limitations

- `cursor()` and `lazy()` bypass batch loading — call `$model->loadSubtypeData()` manually.
- Soft deletes only apply to the parent table; the subtype row remains.
