<?php

namespace App\Module\Operations\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\ShipmentActivityRepository;

#[ORM\Entity(repositoryClass: ShipmentActivityRepository::class)]
#[ORM\Table(name: 'shipment_activity')]
class ShipmentActivity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'json')]
    private array $activities = [];

    #[ORM\Column(length: 16)]
    private string $source = 'INTERNAL';

    public function __construct(int $shipmentId)
    {
        $this->id = $shipmentId;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getActivities(): array
    {
        return $this->activities;
    }

    public function append(
        string $type,
        ?string $subEntityType,
        ?string $subEntityId,
        ?array $changes,
        string $timestamp,
        int $userId
    ): void {
        $copy = $this->activities;
        $copy[] = [$type, $subEntityType, $subEntityId, $changes, $timestamp, $userId];
        $this->activities = $copy;
    }

    public function getSource(): string { return $this->source; }
    public function setSource(string $v): static { $this->source = $v; return $this; }
}
