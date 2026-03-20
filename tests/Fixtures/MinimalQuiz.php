<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\SubtypeModel;

class MinimalQuiz extends SubtypeModel
{
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

    // Only subtype attributes in $fillable — parent attrs should be inherited
    protected $fillable = [
        'passing_score',
        'time_limit',
        'show_correct_answers',
        'category_id',
    ];
}
