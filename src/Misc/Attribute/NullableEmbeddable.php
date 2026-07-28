<?php

namespace App\Misc\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class NullableEmbeddable
{
    public function __construct(
    ) {
    }
}