<?php

namespace App\Misc\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ContainsNullableEmbeddable
{
    public function __construct(
    ) {
    }
}