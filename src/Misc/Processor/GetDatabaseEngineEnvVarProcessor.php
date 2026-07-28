<?php
namespace App\Misc\Processor;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class GetDatabaseEngineEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $env    = $getEnv($name);
        $scheme = preg_match('/^([a-zA-Z][a-zA-Z0-9+\-.]*):/u', $env, $m) ? strtolower($m[1]) : '';

        if (str_contains($scheme, 'sqlite')) {
            return 'sqlite';
        }
        if (str_contains($scheme, 'pgsql') || str_contains($scheme, 'postgresql') || str_contains($scheme, 'postgres')) {
            return 'postgresql';
        }
        return 'mysql';
    }

    public static function getProvidedTypes(): array
    {
        // Define the prefix and the expected type of the processed value
        return [
            'getDatabaseEngine' => 'string', // 'my_custom_processor' is the prefix, 'string' is the type
        ];
    }
}