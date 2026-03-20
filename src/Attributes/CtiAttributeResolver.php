<?php

namespace Pannella\Cti\Attributes;

use ReflectionClass;

class CtiAttributeResolver
{
    /** @var array<class-string, SubtypeConfig|null> */
    private static array $subtypeConfigCache = [];

    /** @var array<class-string, Subtype|null> */
    private static array $subtypeCache = [];

    /**
     * Resolve a SubtypeConfig attribute from a parent model class.
     *
     * @param class-string $class
     * @return SubtypeConfig|null
     */
    public static function resolveSubtypeConfig(string $class): ?SubtypeConfig
    {
        if (array_key_exists($class, self::$subtypeConfigCache)) {
            return self::$subtypeConfigCache[$class];
        }

        $ref = new ReflectionClass($class);
        $attrs = $ref->getAttributes(SubtypeConfig::class);

        self::$subtypeConfigCache[$class] = !empty($attrs)
            ? $attrs[0]->newInstance()
            : null;

        return self::$subtypeConfigCache[$class];
    }

    /**
     * Resolve a Subtype attribute from a subtype model class.
     *
     * @param class-string $class
     * @return Subtype|null
     */
    public static function resolveSubtype(string $class): ?Subtype
    {
        if (array_key_exists($class, self::$subtypeCache)) {
            return self::$subtypeCache[$class];
        }

        $ref = new ReflectionClass($class);
        $attrs = $ref->getAttributes(Subtype::class);

        self::$subtypeCache[$class] = !empty($attrs)
            ? $attrs[0]->newInstance()
            : null;

        return self::$subtypeCache[$class];
    }

    /**
     * Clear all cached resolutions.
     */
    public static function clearCache(): void
    {
        self::$subtypeConfigCache = [];
        self::$subtypeCache = [];
    }
}
