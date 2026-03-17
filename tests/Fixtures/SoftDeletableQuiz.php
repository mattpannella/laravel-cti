<?php

namespace Pannella\Cti\Tests\Fixtures;

use Illuminate\Database\Eloquent\SoftDeletes;
use Pannella\Cti\SubtypeModel;

class SoftDeletableQuiz extends SubtypeModel
{
    use SoftDeletes;

    protected $table = "assessment";
    protected $primaryKey = "id";
    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeKeyName = 'assessment_id';
    protected $ctiParentClass = Assessment::class;

    protected $subtypeAttributes = [
        'passing_score',
        'time_limit',
        'show_correct_answers',
        'category_id',
    ];

    protected $casts = [
        'passing_score' => 'integer',
        'time_limit' => 'integer',
        'show_correct_answers' => 'boolean',
    ];

    protected $attributes = [
        'show_correct_answers' => false
    ];

    protected $fillable = [
        'type_id',
        'title',
        'description',
        'enabled',
        'passing_score',
        'time_limit',
        'show_correct_answers',
        'category_id',
    ];
}
