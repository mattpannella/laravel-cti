<?php

namespace Pannella\Cti\Exceptions;

class SubtypeException extends CtiException
{
    public static function missingTable(): self
    {
        return new static('Subtype table must be defined.');
    }

    public static function missingTypeId(string $model): self
    {
        return new static("Missing type ID for model {$model}");
    }

    public static function invalidSubtype(string $label): self
    {
        return new static("Invalid subtype label: {$label}");
    }

    public static function missingLookupTable(): self
    {
        return new static('Subtypes require a defined lookup table.');
    }

    public static function saveFailed(string $table): self
    {
        return new static("Failed to save subtype data to table: {$table}");
    }
}