<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\SubtypeModel;

class DirectOverlappingColumnsQuiz extends SubtypeModel
{
    protected $table = 'direct_assessment';
    protected $primaryKey = 'id';
    protected $subtypeTable = 'direct_assessment_quiz';
    protected $subtypeKeyName = 'assessment_id';
    protected $ctiParentClass = DirectAssessment::class;

    // 'title' exists on the parent table — this is the overlap
    protected $subtypeAttributes = [
        'passing_score',
        'time_limit',
        'title',
    ];

    protected $fillable = [
        'type',
        'title',
        'passing_score',
        'time_limit',
    ];
}
