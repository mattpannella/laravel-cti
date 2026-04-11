# Getting Started

## Database Schema

CTI requires a **parent table** for shared attributes and one or more **subtype tables** for type-specific attributes. For type resolution, you can use either a **lookup table** (maps integer IDs to type labels) or a **direct discriminator column** (stores the type label as a string directly on the parent table).

### Choosing Between Lookup Table and Direct Discriminator

| | Lookup Table | Direct Discriminator |
|---|---|---|
| **Schema** | Separate table mapping IDs to labels; parent table has an integer FK | No extra table; parent table has a string column |
| **Referential integrity** | FK constraint on the discriminator column | No FK constraint (string values are convention-based) |
| **Performance** | One extra query on first access (batch-loads all rows, then cached for the request) | No extra query; the label is read directly from the column |
| **Adding new types** | Insert a row in the lookup table and add the mapping to `$subtypeMap` | Just add the mapping to `$subtypeMap` |
| **Storage** | Integer column (smaller) | String column (larger, repeated per row) |

Use a **lookup table** if you want the database to enforce valid type values via foreign key constraints. Use a **direct discriminator** if you want a simpler schema and fewer tables, and you're comfortable managing type validity in application code.

### With Lookup Table

```php
// Lookup table: stores the type definitions
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

// Subtype table: quiz-specific attributes
// The primary key references the parent table's primary key
Schema::create('assessment_quiz', function (Blueprint $table) {
    $table->unsignedBigInteger('assessment_id')->primary();
    $table->integer('passing_score')->nullable();
    $table->integer('time_limit')->nullable();
    $table->boolean('show_correct_answers')->default(false);

    $table->foreign('assessment_id')->references('id')->on('assessments')->onDelete('cascade');
});

// Subtype table: survey-specific attributes
Schema::create('assessment_survey', function (Blueprint $table) {
    $table->unsignedBigInteger('assessment_id')->primary();
    $table->boolean('anonymous')->default(false);

    $table->foreign('assessment_id')->references('id')->on('assessments')->onDelete('cascade');
});
```

### Without Lookup Table (Direct Discriminator)

If you prefer a simpler schema, the parent table can store the type label directly in a string column instead of a foreign key to a lookup table:

```php
// Parent table: type stored as a string column
Schema::create('assessments', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('type');  // stores 'quiz', 'survey', etc. directly
    $table->timestamps();
});

// Subtype tables remain the same
Schema::create('assessment_quiz', function (Blueprint $table) {
    $table->unsignedBigInteger('assessment_id')->primary();
    $table->integer('passing_score')->nullable();
    $table->integer('time_limit')->nullable();
    $table->boolean('show_correct_answers')->default(false);

    $table->foreign('assessment_id')->references('id')->on('assessments')->onDelete('cascade');
});
```

## Parent Model

With a lookup table:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Traits\HasSubtypes;

class Assessment extends Model
{
    use HasSubtypes;

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

Without a lookup table (direct discriminator):

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Traits\HasSubtypes;

class Assessment extends Model
{
    use HasSubtypes;

    protected static $subtypeMap = [
        'quiz' => Quiz::class,
        'survey' => Survey::class,
    ];

    protected static $subtypeKey = 'type';  // Column contains the label directly

    // No $subtypeLookupTable, $subtypeLookupKey, or $subtypeLookupLabel needed

    protected $fillable = ['title', 'type'];
}
```

| Property | Description |
|----------|-------------|
| `$subtypeMap` | Maps type labels to subtype class names |
| `$subtypeKey` | Discriminator column on the parent table (FK to lookup table, or direct string label) |
| `$subtypeLookupTable` | *(Optional)* Table containing type definitions. Omit for direct discriminator mode |
| `$subtypeLookupKey` | *(Optional)* Primary key column in the lookup table |
| `$subtypeLookupLabel` | *(Optional)* Label column in the lookup table (values must match `$subtypeMap` keys) |

## Subtype Model

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

    // Only subtype attributes needed; parent's $fillable is auto-inherited
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
| `$fillable` | Only subtype attributes needed; parent attributes are auto-inherited (see below) |
| `$inheritParentFillable` | *(Optional)* Set to `false` to disable automatic `$fillable` inheritance from parent. Default: `true` |
| `$excludeParentFillable` | *(Optional)* Array of parent `$fillable` attributes to exclude from inheritance |

### Automatic `$fillable` and `$casts` Inheritance

Subtype models automatically inherit `$fillable` and `$casts` from their CTI parent model. You only need to declare subtype-specific attributes:

```php
class Quiz extends SubtypeModel
{
    protected $table = 'assessments';
    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeAttributes = ['passing_score', 'time_limit', 'show_correct_answers'];
    protected $ctiParentClass = Assessment::class;

    // Only subtype attributes needed; parent's $fillable is auto-inherited
    protected $fillable = [
        'passing_score',
        'time_limit',
        'show_correct_answers',
    ];

    // Only subtype casts needed; parent's $casts is auto-inherited
    protected $casts = [
        'passing_score' => 'integer',
        'show_correct_answers' => 'boolean',
    ];
}
```

**Precedence:**
- `$casts`: Parent casts are merged first, then subtype casts are applied on top. Subtype wins on conflicts.
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

> **Note:** `$guarded` is never merged. It's a security boundary and must be set explicitly on each model.

The discriminator column is **auto-assigned on create**, so you don't need to set it manually. The `BootsSubtypeModel` trait resolves the correct value from the lookup table (or uses the label string directly in direct discriminator mode) based on the `$subtypeMap`. If the discriminator is already set, it won't be overridden.

## PHP 8.1 Attribute-Based Configuration

As an alternative to class properties, you can configure CTI models using PHP 8.1 attributes. This keeps all configuration in a single, declarative annotation at the top of your class.

**Parent model with `#[SubtypeConfig]` (with lookup table):**

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

**Parent model with `#[SubtypeConfig]` (direct discriminator, no lookup table):**

```php
#[SubtypeConfig(
    map: ['quiz' => Quiz::class, 'survey' => Survey::class],
    key: 'type',
)]
class Assessment extends Model
{
    use HasSubtypes;

    protected $fillable = ['title', 'type'];
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

    // Only subtype attributes needed; parent's $fillable is auto-inherited
    protected $fillable = [
        'passing_score',
        'time_limit',
        'show_correct_answers',
    ];
}
```

| Attribute | Target | Parameters |
|-----------|--------|------------|
| `#[SubtypeConfig]` | Parent model | `map`, `key`, `lookupTable` (optional), `lookupKey` (optional), `lookupLabel` (optional) |
| `#[Subtype]` | Subtype model | `table`, `attributes`, `parentClass`, `keyName` (optional), `inheritParentFillable` (optional), `excludeParentFillable` (optional) |

**Precedence:** When both a class property and an attribute are defined, the **property takes precedence**. This lets you use attributes as defaults and override individual values with properties when needed.

**Performance:** Attribute resolution uses reflection, but results are cached per-class for the lifetime of the request. There is no performance difference after the first access.
