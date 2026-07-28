<?php
declare(strict_types=1);
namespace App\Module\Compliance\Entity;

use App\Module\Compliance\Repository\SystemAuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemAuditLogRepository::class)]
#[ORM\Table(name: 'system_audit_log')]
#[ORM\Index(columns: ['actor_id', 'logged_at'], name: 'IDX_sal_actor')]
#[ORM\Index(columns: ['object_type', 'object_id', 'logged_at'], name: 'IDX_sal_object')]
#[ORM\Index(columns: ['event_type', 'logged_at'], name: 'IDX_sal_type')]
class SystemAuditLog
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $eventType;

    #[ORM\Column(length: 16)]
    private string $actorType = 'SYSTEM';

    #[ORM\Column(nullable: true)]
    private ?int $actorId = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $actorEmail = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $actorIp = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $actorUserAgent = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $objectType = null;

    #[ORM\Column(nullable: true)]
    private ?int $objectId = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $objectRef = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $actionDetail = null;

    #[ORM\Column(length: 8)]
    private string $result = 'SUCCESS';

    #[ORM\Column(nullable: true)]
    private ?int $branchId = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $requestId = null;

    #[ORM\Column]
    private \DateTime $loggedAt;

    public function getId(): ?int { return $this->id; }

    public function getEventType(): string { return $this->eventType; }
    public function setEventType(string $v): static { $this->eventType = $v; return $this; }

    public function getActorType(): string { return $this->actorType; }
    public function setActorType(string $v): static { $this->actorType = $v; return $this; }

    public function getActorId(): ?int { return $this->actorId; }
    public function setActorId(?int $v): static { $this->actorId = $v; return $this; }

    public function getActorEmail(): ?string { return $this->actorEmail; }
    public function setActorEmail(?string $v): static { $this->actorEmail = $v; return $this; }

    public function getActorIp(): ?string { return $this->actorIp; }
    public function setActorIp(?string $v): static { $this->actorIp = $v; return $this; }

    public function getActorUserAgent(): ?string { return $this->actorUserAgent; }
    public function setActorUserAgent(?string $v): static { $this->actorUserAgent = $v; return $this; }

    public function getObjectType(): ?string { return $this->objectType; }
    public function setObjectType(?string $v): static { $this->objectType = $v; return $this; }

    public function getObjectId(): ?int { return $this->objectId; }
    public function setObjectId(?int $v): static { $this->objectId = $v; return $this; }

    public function getObjectRef(): ?string { return $this->objectRef; }
    public function setObjectRef(?string $v): static { $this->objectRef = $v; return $this; }

    public function getActionDetail(): ?array { return $this->actionDetail; }
    public function setActionDetail(?array $v): static { $this->actionDetail = $v; return $this; }

    public function getResult(): string { return $this->result; }
    public function setResult(string $v): static { $this->result = $v; return $this; }

    public function getBranchId(): ?int { return $this->branchId; }
    public function setBranchId(?int $v): static { $this->branchId = $v; return $this; }

    public function getRequestId(): ?string { return $this->requestId; }
    public function setRequestId(?string $v): static { $this->requestId = $v; return $this; }

    public function getLoggedAt(): \DateTime { return $this->loggedAt; }
    public function setLoggedAt(\DateTime $v): static { $this->loggedAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'eventType'      => $this->eventType,
            'actorType'      => $this->actorType,
            'actorId'        => $this->actorId,
            'actorEmail'     => $this->actorEmail,
            'actorIp'        => $this->actorIp,
            'objectType'     => $this->objectType,
            'objectId'       => $this->objectId,
            'objectRef'      => $this->objectRef,
            'actionDetail'   => $this->actionDetail,
            'result'         => $this->result,
            'branchId'       => $this->branchId,
            'requestId'      => $this->requestId,
            'loggedAt'       => $this->loggedAt->format('Y-m-d H:i:s'),
        ];
    }
}
