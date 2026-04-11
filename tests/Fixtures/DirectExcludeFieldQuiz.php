<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\SubtypeModel;

class DirectExcludeFieldQuiz extends SubtypeModel
{
    protected $table = 'direct_assessment';
    protected $primaryKey = 'id';
    protected $subtypeTable = 'direct_assessment_quiz';
    protected $subtypeKeyName = 'assessment_id';
    protected $ctiParentClass = DirectAssessment::class;

    protected array $excludeParentFillable = ['description'];

    protected $subtypeAttributes = [
        'passing_score',
        'time_limit',
    ];

    protected $casts = [
        'passing_score' => 'integer',
        'time_limit' => 'integer',
    ];

    protected $fillable = [
        'passing_score',
        'time_limit',
    ];
}
