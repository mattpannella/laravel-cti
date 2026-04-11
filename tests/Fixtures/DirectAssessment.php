<?php

namespace Pannella\Cti\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Traits\HasSubtypes;

class DirectAssessment extends Model
{
    use HasSubtypes;

    protected $table = 'direct_assessment';

    protected static $subtypeMap = [
        'quiz' => DirectQuiz::class,
        'survey' => DirectSurvey::class,
    ];

    protected static $subtypeKey = 'type';

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

    public function tags()
    {
        return $this->hasMany(DirectAssessmentTag::class, 'assessment_id');
    }
}
