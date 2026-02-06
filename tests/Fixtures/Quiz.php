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
        'show_correct_answers',
        'category_id',
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
        'show_correct_answers',
        'category_id',
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

    /**
     * Quiz has one settings record (using subtype relationship).
     */
    public function settings()
    {
        return $this->subtypeHasOne(QuizSettings::class);
    }

    /**
     * Quiz belongs to a category (using subtype relationship).
     */
    public function category()
    {
        return $this->subtypeBelongsTo(QuizCategory::class, 'category_id');
    }

    /**
     * Quiz belongs to many students (using subtype relationship).
     */
    public function students()
    {
        return $this->subtypeBelongsToMany(Student::class, 'quiz_student', 'assessment_id', 'student_id');
    }
}