<?php

namespace Pannella\Cti\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    protected $table = 'quiz_attempts';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'assessment_id',
        'student_name',
        'score'
    ];
}
