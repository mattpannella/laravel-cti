<?php

namespace Pannella\Cti\Exceptions;

/**
 * Base exception class for CTI-related errors.
 * 
 * All CTI-specific exceptions extend from this base class to allow
 * for catch-all exception handling of CTI operations.
 */
class CtiException extends \RuntimeException
{
}