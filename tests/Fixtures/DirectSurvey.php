<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\SubtypeModel;

class DirectSurvey extends SubtypeModel
{
    protected $table = 'direct_assessment';
    protected $primaryKey = 'id';
    protected $subtypeTable = 'direct_assessment_survey';
    protected $subtypeKeyName = 'assessment_id';
    protected $ctiParentClass = DirectAssessment::class;

    protected $subtypeAttributes = [
        'anonymous',
        'allow_multiple_responses',
    ];

    protected $attributes = [
        'anonymous' => false,
        'allow_multiple_responses' => false,
    ];

    protected $fillable = [
        'type',
        'title',
        'description',
        'enabled',
        'anonymous',
        'allow_multiple_responses',
    ];
}
