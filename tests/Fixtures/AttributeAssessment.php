<?php

namespace Pannella\Cti\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Attributes\SubtypeConfig;
use Pannella\Cti\Traits\HasSubtypes;

#[SubtypeConfig(
    map: ['quiz' => AttributeQuiz::class, 'survey' => AttributeSurvey::class],
    key: 'type_id',
    lookupTable: 'assessment_type',
    lookupKey: 'id',
    lookupLabel: 'label',
)]
class AttributeAssessment extends Model
{
    use HasSubtypes;

    protected $table = 'assessment';

    protected $fillable = [
        'type_id',
        'title',
        'description',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
