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
     */
    public function __construct(
        public readonly string $table,
        public readonly array $attributes,
        public readonly string $parentClass,
        public readonly ?string $keyName = null,
    ) {}
}
