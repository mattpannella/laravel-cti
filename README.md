# Laravel Class Table Inheritance (CTI) Package

This package provides a simple and generic way to implement Class Table Inheritance (CTI) in Laravel Eloquent models, allowing you to model a supertype and multiple subtypes stored in separate database tables.

---

## Features

- Automatic morphing of supertype models into appropriate subtype models based on a type discriminator column.
- Seamless saving and updating of both supertype and subtype data.
- Support for Eloquent model events
- Designed to work with normalized database schemas where subtype tables store additional fields.

---

## Installation

Require this package via Composer:

```bash
composer require pannella/laravel-cti
```

## Example Usage

### Database Tables Example

- `assessment` (id, type_id, title, created_at, updated_at)
- `assessment_type` (id, label) — e.g. label = 'quiz' or 'survey'
- `assessment_quiz` (assessment_id, quiz_specific_column_1, quiz_specific_column_2)
- `assessment_survey` (assessment_id, survey_specific_column_1, survey_specific_column_2)

---

### 1. The Supertype Model: `Assessment.php`

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

    protected $fillable = [
        'id',
        'type_id',
        'title',
        'description',
        'created_at',
        'updated_at',
    ];
}
```

### 2. Subtype Models

`AssessmentQuiz.php`
```php
namespace App\Models;

use Pannella\Cti\SubtypedModel;

class AssessmentQuiz extends SubtypedModel
{
    protected $table = 'assessment_quiz';

    protected $subtypeAttributes = [
        'quiz_specific_column_1',
        'quiz_specific_column_2',
    ];

    protected $subtypeTable = 'assessment_quiz';

    // Fillable includes all supertype columns + subtype-specific
    protected $fillable = [
        'id',
        'type_id',
        'title',
        'description',
        'created_at',
        'updated_at',
        'quiz_specific_field1',
        'quiz_specific_field2',
    ];
}
```

`AssessmentSurvey.php`
```php
namespace App\Models;

use Pannella\Cti\SubtypedModel;

class AssessmentSurvey extends SubtypedModel
{
    protected $table = 'assessment_survey';

    protected $subtypeAttributes = [
        'survey_specific_column_1',
        'survey_specific_column_2',
    ];

    protected $subtypeTable = 'assessment_survey';

    protected $fillable = [
        'id',
        'type_id',
        'title',
        'description',
        'created_at',
        'updated_at',
        'survey_specific_field1',
        'survey_specific_field2',
    ];
}
```
### 4. Using the models
```php
use App\Models\Assessment;

// Fetch all assessments with subtype data hydrated
$assessments = Assessment::all()->loadSubtypes();

foreach ($assessments as $assessment) {
    echo get_class($assessment) . ": ID {$assessment->id}" . PHP_EOL;

    if ($assessment instanceof AssessmentQuiz) {
        echo "Quiz Column 1: " . $assessment->quiz_specific_column_1 . PHP_EOL;
    }

    if ($assessment instanceof AssessmentSurvey) {
        echo "Survey Column 1: " . $assessment->survey_specific_column_1 . PHP_EOL;
    }
}

// Creating a new quiz assessment
$quiz = new AssessmentQuiz();
$quiz->title = 'Sample Quiz';
$quiz->quiz_specific_column_1 = 'Example value';
$quiz->save();
```

## Why Use CTI Instead of Polymorphic Relations?

### Database Normalization
Unlike Laravel's `morphTo` relationships which store type information in separate columns (`*_type`, `*_id`), CTI follows proper database normalization principles:

- Each entity type has its own dedicated table
- Foreign keys maintain referential integrity
- No string-based type identifiers in the database
- Type information is stored in a lookup table

For example, with `morphTo`:
```sql
assessment
  id
  assessmentable_id
  assessmentable_type -- Stores full class names as strings
  title
  created_at
  updated_at

quiz
  id
  difficulty_level
  time_limit
  created_at
  updated_at

survey
  id
  response_type
  allow_anonymous
  created_at
  updated_at
```

With CTI:
```sql
assessment
  id
  type_id -- Foreign key to assessment_type
  title
  created_at
  updated_at

assessment_type
  id
  label -- 'quiz', 'survey', etc.

assessment_quiz
  assessment_id -- Foreign key to assessments
  difficulty_level
  time_limit

assessment_survey
  assessment_id -- Foreign key to assessments
  response_type
  allow_anonymous
```

### Benefits of CTI

1. **Referential Integrity**: Foreign key constraints ensure data consistency
2. **Type Safety**: Types are defined in the database, not as strings in code
3. **Query Performance**: Joins are more efficient than polymorphic queries
4. **Schema Evolution**: Easy to add new subtypes without modifying existing tables
5. **Data Validation**: Database-level constraints can be applied to subtype tables
6. **Storage Efficiency**: No null columns for irrelevant attributes

### Database Normalization Issues with morphTo

Laravel's polymorphic relationships break several database normalization rules:

1. **First Normal Form (1NF)**
   - The `*_type` column stores multiple types of values (class names as strings)
   - This violates atomic value requirements of 1NF
   - Example: `assessmentable_type` could be 'App\Models\Quiz' or 'App\Models\Survey'

2. **Second Normal Form (2NF)**
   - The combination of `*_type` and `*_id` creates a composite key with partial dependencies
   - The same ID could refer to different records depending on the type
   - This creates potential referential integrity issues

With CTI, we maintain proper normalization by:
- Having clear foreign key relationships
- Ensuring each attribute depends on the full primary key