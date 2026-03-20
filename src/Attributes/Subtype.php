<?php

namespace Pannella\Cti\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Subtype
{
    /**
     * @param string $table
     * @param array<int, string> $attributes
     * @param class-string $parentClass
     * @param string|null $keyName
     * @param bool|null $inheritParentFillable
     * @param array<int, string>|null $excludeParentFillable
     */
    public function __construct(
        public readonly string $table,
        public readonly array $attributes,
        public readonly string $parentClass,
        public readonly ?string $keyName = null,
        public readonly ?bool $inheritParentFillable = null,
        public readonly ?array $excludeParentFillable = null,
    ) {}
}
