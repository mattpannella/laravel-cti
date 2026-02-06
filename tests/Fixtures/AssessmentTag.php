<?php

namespace Pannella\Cti\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class AssessmentTag extends Model
{
    protected $table = 'assessment_tag';
    public $timestamps = false;
    
    protected $fillable = ['assessment_id', 'tag_name'];
}
