<?php
declare(strict_types=1);
namespace App\Module\Compliance\Service;

use App\Module\Compliance\Entity\SystemAuditLog;
use App\Module\Compliance\Repository\SystemAuditLogRepository;
use Symfony\Component\HttpFoundation\Request;

class AuditService
{
    public function __construct(private readonly SystemAuditLogRepository $repo) {}

    public function log(
        string  $eventType,
        string  $actorType = 'SYSTEM',
        ?int    $actorId = null,
        ?string $actorEmail = null,
        ?string $actorIp = null,
        ?string $objectType = null,
        ?int    $objectId = null,
        ?string $objectRef = null,
        ?array  $actionDetail = null,
        string  $result = 'SUCCESS',
        ?int    $branchId = null,
        ?string $requestId = null,
        ?string $actorUserAgent = null,
    ): SystemAuditLog {
        $entry = new SystemAuditLog();
        $entry->setEventType($eventType);
        $entry->setActorType($actorType);
        $entry->setActorId($actorId);
        $entry->setActorEmail($actorEmail);
        $entry->setActorIp($actorIp);
        $entry->setActorUserAgent($actorUserAgent);
        $entry->setObjectType($objectType);
        $entry->setObjectId($objectId);
        $entry->setObjectRef($objectRef);
        $entry->setActionDetail($actionDetail);
        $entry->setResult($result);
        $entry->setBranchId($branchId);
        $entry->setRequestId($requestId);
        $entry->setLoggedAt(new \DateTime());

        $this->repo->insert($entry);
        return $entry;
    }

    public function logFromRequest(
        Request $request,
        string  $eventType,
        string  $actorType = 'USER',
        ?int    $actorId = null,
        ?string $actorEmail = null,
        ?string $objectType = null,
        ?int    $objectId = null,
        ?string $objectRef = null,
        ?array  $actionDetail = null,
        string  $result = 'SUCCESS',
        ?int    $branchId = null,
    ): SystemAuditLog {
        return $this->log(
            eventType:     $eventType,
            actorType:     $actorType,
            actorId:       $actorId,
            actorEmail:    $actorEmail,
            actorIp:       $request->getClientIp(),
            objectType:    $objectType,
            objectId:      $objectId,
            objectRef:     $objectRef,
            actionDetail:  $actionDetail,
            result:        $result,
            branchId:      $branchId,
            requestId:     $request->headers->get('X-Request-Id'),
            actorUserAgent: $request->headers->get('User-Agent'),
        );
    }
}
