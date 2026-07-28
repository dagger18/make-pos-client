<?php
namespace App\Misc\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AppModule
{
    public function __construct(public readonly string $name) {}
}
