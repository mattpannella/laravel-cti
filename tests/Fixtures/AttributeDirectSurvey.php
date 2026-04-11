<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\Attributes\Subtype;
use Pannella\Cti\SubtypeModel;

#[Subtype(
    table: 'direct_assessment_survey',
    attributes: ['anonymous', 'allow_multiple_responses'],
    parentClass: AttributeDirectAssessment::class,
    keyName: 'assessment_id',
)]
class AttributeDirectSurvey extends SubtypeModel
{
    protected $table = 'direct_assessment';
    protected $primaryKey = 'id';

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
