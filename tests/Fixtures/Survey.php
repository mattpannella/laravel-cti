<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\SubtypeModel;

class Survey extends SubtypeModel
{
    protected $table = "assessment";
    protected $primaryKey = "id";
    protected $subtypeTable = 'assessment_survey';
    protected $subtypeKeyName = 'assessment_id';
    protected $ctiParentClass = Assessment::class;

    protected $subtypeAttributes = [
        'anonymous',
        'allow_multiple_responses',
    ];

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
