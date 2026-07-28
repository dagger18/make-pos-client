<?php

namespace App\Module\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class InterServiceTokenService
{
    public function __construct(
        private ParameterBagInterface $params,
    ) {}

    public function generate(): string
    {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(8));
        $payload = $timestamp . '.' . $nonce;
        $signature = hash_hmac('sha256', $payload, $this->params->get('master_api_key'));

        return $payload . '.' . $signature;
    }

    public function validate(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$timestamp, $nonce, $signature] = $parts;

        if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 10) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $nonce, $this->params->get('master_api_key'));

        return hash_equals($expected, $signature);
    }
}
