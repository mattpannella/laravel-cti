<?php

namespace Pannella\Cti\Exceptions;

/**
 * Exception class for subtype-specific errors.
 * 
 * Provides static factory methods for creating common subtype-related
 * error instances with descriptive messages.
 */
class SubtypeException extends CtiException
{
    /**
     * Create an exception for missing subtype table definition.
     *
     * @return static
     */
    public static function missingTable(): self
    {
        return new static('Subtype table must be defined.');
    }

    /**
     * Create an exception for missing type ID.
     *
     * @param string $model The model class name
     * @return static
     */
    public static function missingTypeId(string $model): self
    {
        return new static("Missing type ID for model {$model}");
    }

    /**
     * Create an exception for invalid subtype label.
     *
     * @param string $label The invalid label
     * @return static
     */
    public static function invalidSubtype(string $label): self
    {
        return new static("Invalid subtype label: {$label}");
    }

    /**
     * Create an exception for missing lookup table configuration.
     *
     * @return static
     */
    public static function missingLookupTable(): self
    {
        return new static('Subtypes require a defined lookup table.');
    }

    /**
     * Create an exception for failed save operation.
     *
     * @param string $table The table where the save failed
     * @return static
     */
    public static function saveFailed(string $table): self
    {
        return new static("Failed to save subtype data to table: {$table}");
    }
}