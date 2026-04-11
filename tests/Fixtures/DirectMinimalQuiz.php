<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\SubtypeModel;

class DirectMinimalQuiz extends SubtypeModel
{
    protected $table = 'direct_assessment';
    protected $primaryKey = 'id';
    protected $subtypeTable = 'direct_assessment_quiz';
    protected $subtypeKeyName = 'assessment_id';
    protected $ctiParentClass = DirectAssessment::class;

    protected $subtypeAttributes = [
        'passing_score',
        'time_limit',
    ];

    protected $casts = [
        'passing_score' => 'integer',
        'time_limit' => 'integer',
    ];

    // Only subtype attributes in $fillable — parent attrs should be inherited
    protected $fillable = [
        'passing_score',
        'time_limit',
    ];
}
