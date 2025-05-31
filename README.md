# Laravel Class Table Inheritance (CTI) Package

This package provides a simple and generic way to implement Class Table Inheritance (CTI) in Laravel Eloquent models, allowing you to model a supertype and multiple subtypes stored in separate database tables.

---

## Features

- Automatic morphing of supertype models into appropriate subtype models based on a type discriminator column.
- Seamless saving and updating of both supertype and subtype data.
- Shared relationships can be defined in a common trait, simplifying eager loading.
- Designed to work with normalized database schemas where subtype tables store additional fields.
- Compatible with Laravel's Eloquent ORM.

---

## Installation

Require this package via Composer:

```bash
composer require pannella/laravel-cti
```

## Example Usage

### Database Tables Example

- `assessments` (id, type_id, title, created_at, updated_at)
- `assessment_types` (id, label) — e.g. label = 'quiz' or 'survey'
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