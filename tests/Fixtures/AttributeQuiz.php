<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\Attributes\Subtype;
use Pannella\Cti\SubtypeModel;

#[Subtype(
    table: 'assessment_quiz',
    attributes: ['passing_score', 'time_limit', 'show_correct_answers', 'category_id'],
    parentClass: AttributeAssessment::class,
    keyName: 'assessment_id',
)]
class AttributeQuiz extends SubtypeModel
{
    protected $table = 'assessment';
    protected $primaryKey = 'id';

    protected $casts = [
        'passing_score' => 'integer',
        'time_limit' => 'integer',
        'show_correct_answers' => 'boolean',
    ];

    protected $attributes = [
        'show_correct_answers' => false,
    ];

    protected $fillable = [
        'type_id',
        'title',
        'description',
        'enabled',
        'passing_score',
        'time_limit',
        'show_correct_answers',
        'category_id',
    ];
}
