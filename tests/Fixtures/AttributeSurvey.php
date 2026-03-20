<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\Attributes\Subtype;
use Pannella\Cti\SubtypeModel;

#[Subtype(
    table: 'assessment_survey',
    attributes: ['anonymous', 'allow_multiple_responses'],
    parentClass: AttributeAssessment::class,
    keyName: 'assessment_id',
)]
class AttributeSurvey extends SubtypeModel
{
    protected $table = 'assessment';
    protected $primaryKey = 'id';

    protected $attributes = [
        'anonymous' => false,
        'allow_multiple_responses' => false,
    ];

    protected $fillable = [
        'type_id',
        'title',
        'description',
        'enabled',
        'anonymous',
        'allow_multiple_responses',
    ];
}
