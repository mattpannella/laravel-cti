<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\SubtypeModel;

class DirectQuiz extends SubtypeModel
{
    protected $table = 'direct_assessment';
    protected $primaryKey = 'id';
    protected $subtypeTable = 'direct_assessment_quiz';
    protected $subtypeKeyName = 'assessment_id';
    protected $ctiParentClass = DirectAssessment::class;

    protected $subtypeAttributes = [
        'passing_score',
        'time_limit',
    ];

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

    public function questions()
    {
        return $this->subtypeHasMany(DirectQuizQuestion::class);
    }

    public function settings()
    {
        return $this->subtypeHasOne(DirectQuizSettings::class);
    }
}
