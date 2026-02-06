<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\SubtypeModel;

/**
 * A SubtypeModel fixture with subtypeAttributes that overlap with the parent table.
 * Used to test that overlapping column validation throws an exception.
 */
class OverlappingColumnsQuiz extends SubtypeModel
{
    protected $table = "assessment";
    protected $primaryKey = "id";
    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeKeyName = 'assessment_id';
    protected $ctiParentClass = Assessment::class;

    // 'title' exists on the parent table (assessment) — this is the overlap
    protected $subtypeAttributes = [
        'passing_score',
        'time_limit',
        'title',
    ];

    protected $fillable = [
        'type_id',
        'title',
        'passing_score',
        'time_limit',
    ];
}
