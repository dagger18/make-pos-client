<?php
namespace App\Module\Integration\Repository;

use App\Module\Integration\Entity\PortalToken;

/**
 * Stub — portal token feature not yet implemented for POS.
 */
class PortalTokenRepository
{
    public function findValidToken(string $rawToken): ?PortalToken
    {
        return null;
    }
}
