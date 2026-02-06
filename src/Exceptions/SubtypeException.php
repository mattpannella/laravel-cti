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
     * Create an exception for a missing configuration property on a model.
     *
     * @param string $class The model class name
     * @param string $property The missing property name
     * @return static
     */
    public static function missingConfiguration(string $class, string $property): self
    {
        return new static("Missing CTI configuration property \${$property} on {$class}.");
    }

    /**
     * Create an exception for failed type resolution from the lookup table.
     *
     * @param string $label The subtype label that could not be resolved
     * @param string $table The lookup table that was queried
     * @return static
     */
    public static function typeResolutionFailed(string $label, string $table): self
    {
        return new static("Could not resolve type ID for label '{$label}' in table '{$table}'.");
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