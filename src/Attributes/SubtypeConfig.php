<?php

namespace Pannella\Cti\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class SubtypeConfig
{
    /**
     * @param array<string, class-string> $map
     * @param string $key
     * @param string|null $lookupTable
     * @param string|null $lookupKey
     * @param string|null $lookupLabel
     */
    public function __construct(
        public readonly array $map,
        public readonly string $key,
        public readonly ?string $lookupTable = null,
        public readonly ?string $lookupKey = null,
        public readonly ?string $lookupLabel = null,
    ) {}
}
