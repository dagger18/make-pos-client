<?php

namespace App\Misc\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class CheckOwnership
{
    public function __construct(
        public array $ownershipFields,
    ) {
    }
}