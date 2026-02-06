<?php

namespace Pannella\Cti\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pannella\Cti\Traits\HasSubtypes;

/**
 * A deliberately misconfigured model for testing exception handling.
 * Missing required static properties like $subtypeKey.
 */
class MisconfiguredAssessment extends Model
{
    use HasSubtypes;

    protected $table = 'assessment';

    protected static $subtypeMap = [];
    protected static $subtypeLookupTable = 'assessment_type';
    protected static $subtypeLookupKey = 'id';
    protected static $subtypeLookupLabel = 'label';
    // Deliberately missing: $subtypeKey
}
