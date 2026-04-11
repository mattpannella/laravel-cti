<?php

namespace Pannella\Cti\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Attributes\SubtypeConfig;
use Pannella\Cti\Traits\HasSubtypes;

#[SubtypeConfig(
    map: ['quiz' => AttributeDirectQuiz::class, 'survey' => AttributeDirectSurvey::class],
    key: 'type',
)]
class AttributeDirectAssessment extends Model
{
    use HasSubtypes;

    protected $table = 'direct_assessment';

    protected $fillable = [
        'type',
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
