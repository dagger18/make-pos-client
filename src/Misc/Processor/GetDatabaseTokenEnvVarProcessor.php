<?php
namespace App\Misc\Processor;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class GetDatabaseTokenEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $url = $getEnv($name);

        if (str_starts_with($url, 'sqlite://')) {
            // sqlite:///var/sqlite/<token>.db → <token>
            if (preg_match('#/([^/]+)\.db$#', $url, $m)) {
                return $m[1];
            }
            return '';
        }

        // mysql://user:pass@host:port/mc_<token> → <token>
        $path   = parse_url($url, PHP_URL_PATH) ?? '';
        $dbName = ltrim($path, '/');
        if (str_starts_with($dbName, 'mc_')) {
            return substr($dbName, 3);
        }
        return $dbName;
    }

    public static function getProvidedTypes(): array
    {
        return [
            'getDatabaseToken' => 'string',
        ];
    }
}
