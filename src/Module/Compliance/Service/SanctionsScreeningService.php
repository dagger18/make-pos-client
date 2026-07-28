<?php
declare(strict_types=1);
namespace App\Module\Compliance\Service;

use App\Module\Compliance\Repository\SanctionsListRepository;

class SanctionsScreeningService
{
    public function __construct(
        private readonly SanctionsListRepository $repo,
        private readonly AuditService            $auditService,
    ) {}

    /**
     * @return array{status: string, exactMatches: array, fuzzyMatches: array, totalChecked: int}
     *   status: CLEAR | POSSIBLE_MATCH | CONFIRMED_HIT
     */
    public function check(string $orgName, ?string $countryCode = null): array
    {
        $exactMatches = $this->repo->findByName($orgName);
        $fuzzyMatches = [];

        if (empty($exactMatches)) {
            $rawFuzzy = $this->repo->fuzzyMatch($orgName);
            foreach ($rawFuzzy as $m) {
                $fuzzyMatches[] = array_merge($m['entry']->toArray(), ['score' => $m['score']]);
            }
        }

        $status = match (true) {
            !empty($exactMatches) => 'CONFIRMED_HIT',
            !empty($fuzzyMatches) => 'POSSIBLE_MATCH',
            default               => 'CLEAR',
        };

        $this->auditService->log(
            eventType:    'COMPLIANCE.SANCTIONS_CHECK',
            actorType:    'SYSTEM',
            objectType:   'organisation',
            objectRef:    $orgName,
            actionDetail: [
                'orgName'       => $orgName,
                'countryCode'   => $countryCode,
                'result'        => $status,
                'exactMatches'  => count($exactMatches),
                'fuzzyMatches'  => count($fuzzyMatches),
            ],
            result: $status === 'CLEAR' ? 'SUCCESS' : 'BLOCKED',
        );

        return [
            'orgName'       => $orgName,
            'countryCode'   => $countryCode,
            'status'        => $status,
            'exactMatches'  => array_map(fn($e) => $e->toArray(), $exactMatches),
            'fuzzyMatches'  => $fuzzyMatches,
            'totalChecked'  => count($exactMatches) + count($fuzzyMatches),
        ];
    }
}
