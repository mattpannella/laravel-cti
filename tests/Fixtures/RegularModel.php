<?php

namespace Pannella\Cti\Tests\Fixtures;

use Pannella\Cti\SubtypeModel;

/**
 * A SubtypeModel that acts like a regular model (no subtype table/attributes defined).
 */
class RegularModel extends SubtypeModel
{
    protected $table = "assessment";
    protected $primaryKey = "id";
    
    // Note: No subtypeTable or subtypeAttributes defined
    // This should make it behave like a normal Eloquent model

    protected $fillable = [
        'type_id',
        'title',
        'description',
        'enabled'
    ];
}
