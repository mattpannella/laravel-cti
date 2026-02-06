<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\SubtypeModel;

class Quiz extends SubtypeModel
{
    protected $table = "assessment";
    protected $primaryKey = "id";
    protected $subtypeTable = 'assessment_quiz';
    protected $subtypeKeyName = 'assessment_id';
    protected $ctiParentClass = Assessment::class;

    protected $subtypeAttributes = [
        'passing_score',
        'time_limit',
        'show_correct_answers'
    ];

    protected $attributes = [
        'show_correct_answers' => false
    ];

    protected $fillable = [
        'type_id',
        'title',
        'description',
        'enabled',
        'passing_score',
        'time_limit',
        'show_correct_answers'
    ];

    /**
     * Quiz has many questions (using subtype relationship).
     */
    public function questions()
    {
        return $this->subtypeHasMany(QuizQuestion::class);
    }

    /**
     * Quiz has many attempts (using subtype relationship).
     */
    public function attempts()
    {
        return $this->subtypeHasMany(QuizAttempt::class);
    }
}