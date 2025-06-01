<?php

namespace Pannella\Cti\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Traits\HasSubtypes;

class Assessment extends Model
{
    use HasSubtypes;

    protected $table = 'assessment';
    
    // Required static properties for CTI to work
    protected static $subtypeMap = [
        'quiz' => Quiz::class
    ];

    protected $fillable = [
        'type_id',
        'title',
        'description',
        'enabled'
    ];
    
    protected static $subtypeKey = 'type_id';
    protected static $subtypeLookupTable = 'assessment_type';
    protected static $subtypeLookupKey = 'id';
    protected static $subtypeLookupLabel = 'label';
}