<?php

namespace Pannella\Cti\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class DirectQuizSettings extends Model
{
    protected $table = 'direct_quiz_settings';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'assessment_id',
        'randomize_questions',
        'show_progress_bar',
    ];
}
