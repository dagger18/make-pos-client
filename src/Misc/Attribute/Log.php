<?php

namespace App\Misc\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Log
{
    public function __construct(
    ) {
    }
}