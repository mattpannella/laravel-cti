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
     * @return self
     */
    public static function missingTable(): self
    {
        return new self('Subtype table must be defined.');
    }

    /**
     * Create an exception for missing type ID.
     *
     * @param string $model The model class name
     * @return self
     */
    public static function missingTypeId(string $model): self
    {
        return new self("Missing type ID for model {$model}");
    }

    /**
     * Create an exception for invalid subtype label.
     *
     * @param string $label The invalid label
     * @return self
     */
    public static function invalidSubtype(string $label): self
    {
        return new self("Invalid subtype label: {$label}");
    }

    /**
     * Create an exception for missing lookup table configuration.
     *
     * @return self
     */
    public static function missingLookupTable(): self
    {
        return new self('Subtypes require a defined lookup table.');
    }

    /**
     * Create an exception for a missing configuration property on a model.
     *
     * @param string $class The model class name
     * @param string $property The missing property name
     * @return self
     */
    public static function missingConfiguration(string $class, string $property): self
    {
        return new self("Missing CTI configuration property \${$property} on {$class}.");
    }

    /**
     * Create an exception for failed type resolution from the lookup table.
     *
     * @param string $label The subtype label that could not be resolved
     * @param string $table The lookup table that was queried
     * @return self
     */
    public static function typeResolutionFailed(string $label, string $table): self
    {
        return new self("Could not resolve type ID for label '{$label}' in table '{$table}'.");
    }

    /**
     * Create an exception for failed save operation.
     *
     * @param string $table The table where the save failed
     * @return self
     */
    public static function saveFailed(string $table): self
    {
        return new self("Failed to save subtype data to table: {$table}");
    }

    /**
     * Create an exception for overlapping columns between parent and subtype tables.
     *
     * @param string $class The model class name
     * @param array<int, string> $columns The overlapping column names
     * @return self
     */
    public static function overlappingColumns(string $class, array $columns): self
    {
        $cols = implode(', ', $columns);

        return new self(
            "{$class} has \$subtypeAttributes that overlap with parent table columns: {$cols}. "
            . "Subtype attributes must be unique to the subtype table."
        );
    }
}