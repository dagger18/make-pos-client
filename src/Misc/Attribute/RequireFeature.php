<?php
namespace App\Misc\Attribute;

use App\Misc\Enum\Feature;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class RequireFeature
{
    public function __construct(public readonly Feature $feature) {}
}
