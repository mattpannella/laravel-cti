<?php

namespace Pannella\Cti\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class DirectAssessmentTag extends Model
{
    protected $table = 'direct_assessment_tag';
    public $timestamps = false;

    protected $fillable = ['assessment_id', 'tag_name'];
}
