# Laravel CTI

[![Tests](https://github.com/mattpannella/laravel-cti/actions/workflows/tests.yml/badge.svg)](https://github.com/mattpannella/laravel-cti/actions/workflows/tests.yml)

A Laravel package for implementing Class Table Inheritance pattern with Eloquent models. Unlike Laravel's polymorphic relations which denormalize data, CTI maintains proper database normalization by storing shared attributes in a parent table and subtype-specific attributes in separate tables.

## Features

- Automatic model type resolution and instantiation
- Seamless saving/updating across parent and subtype tables
- Automatic batch-loading of subtype data (no N+1 queries)
- Support for Eloquent events and relationships
- Full type safety and referential integrity

## Installation

```bash
composer require pannella/laravel-cti
```

## Usage

### 1. Parent Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Traits\HasSubtypes;

class Assessment extends Model
{
    use HasSubtypes;

    protected static $subtypeMap = [
        'quiz' => AssessmentQuiz::class,
        'survey' => AssessmentSurvey::class,
    ];

    protected static $subtypeKey = 'type_id';
    protected static $subtypeLookupTable = 'assessment_types';
    protected static $subtypeLookupKey = 'id';
    protected static $subtypeLookupLabel = 'label';
}
```

### 2. Subtype Model

```php
namespace App\Models;

use Pannella\Cti\SubtypeModel;

class Quiz extends SubtypeModel
{
    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeAttributes = [
        'passing_score',
        'time_limit',
        'show_correct_answers'
    ];
    
    protected $ctiParentClass = Assessment::class;
}
```

### 3. Using the Models

Subtype data is loaded automatically whenever models are fetched via `get()`, `paginate()`, `find()`, `all()`, etc. No manual calls required.

```php
// Fetch with automatic subtype resolution and batch-loaded subtype data
$assessments = Assessment::all();

// Pagination works seamlessly — subtype data is batch-loaded for the page
$assessments = Assessment::paginate(15);

// Create new subtype instance
$quiz = new Quiz();
$quiz->title = 'Final Exam';        // parent attribute
$quiz->passing_score = 80;          // subtype attribute
$quiz->save();                      // saves to both tables

// Load single instance
$quiz = Quiz::find(1);             // hydrates both parent and subtype data

// Update existing
$quiz->time_limit = 60;
$quiz->save();                     // updates only modified tables

// Query using subtype attributes
$quizzes = Quiz::where('passing_score', '>', 70)->get();
```

### How It Works

Both the parent model (via `HasSubtypes`) and subtype models (via `SubtypeModel`) return a `SubtypedCollection` from `newCollection()`. The collection's constructor automatically batch-loads subtype data using one query per subtype table, eliminating N+1 queries.

**Note:** `cursor()` and `lazy()` bypass `newCollection()` and iterate models individually. For these paths, call `loadSubtypeData()` manually on each model.

## Configuration

### Required Parent Model Properties

| Property | Description |
|----------|-------------|
| `$subtypeMap` | Maps type labels to subtype class names |
| `$subtypeKey` | Foreign key to type lookup table |
| `$subtypeLookupTable` | Table containing type definitions |
| `$subtypeLookupKey` | Primary key in lookup table |
| `$subtypeLookupLabel` | Type label column in lookup table |

### Required Subtype Model Properties

| Property | Description |
|----------|-------------|
| `$subtypeTable` | Table containing subtype-specific fields |
| `$subtypeAttributes` | List of subtype-specific column names |
| `$ctiParentClass` | FQCN of parent model class |

## License

MIT