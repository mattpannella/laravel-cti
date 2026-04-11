<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\Attributes\Subtype;
use Pannella\Cti\SubtypeModel;

#[Subtype(
    table: 'direct_assessment_quiz',
    attributes: ['passing_score', 'time_limit'],
    parentClass: AttributeDirectAssessment::class,
    keyName: 'assessment_id',
)]
class AttributeDirectQuiz extends SubtypeModel
{
    protected $table = 'direct_assessment';
    protected $primaryKey = 'id';

    protected $casts = [
        'passing_score' => 'integer',
        'time_limit' => 'integer',
    ];

    protected $fillable = [
        'type',
        'title',
        'description',
        'enabled',
        'passing_score',
        'time_limit',
    ];
}
