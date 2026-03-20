<?php

namespace Pannella\Cti\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class SubtypeConfig
{
    /**
     * @param array<string, class-string> $map
     * @param string $key
     * @param string $lookupTable
     * @param string $lookupKey
     * @param string $lookupLabel
     */
    public function __construct(
        public readonly array $map,
        public readonly string $key,
        public readonly string $lookupTable,
        public readonly string $lookupKey,
        public readonly string $lookupLabel,
    ) {}
}
