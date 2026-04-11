<?php

namespace Pannella\Cti\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class DirectQuizQuestion extends Model
{
    protected $table = 'direct_quiz_questions';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'assessment_id',
        'question_text',
        'points',
    ];
}
