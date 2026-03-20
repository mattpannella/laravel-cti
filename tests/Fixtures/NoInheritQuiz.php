<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\SubtypeModel;

class NoInheritQuiz extends SubtypeModel
{
    protected $table = "assessment";
    protected $primaryKey = "id";
    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeKeyName = 'assessment_id';
    protected $ctiParentClass = Assessment::class;

    protected bool $inheritParentFillable = false;

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

    protected $fillable = [
        'passing_score',
        'time_limit',
        'show_correct_answers',
        'category_id',
    ];
}
