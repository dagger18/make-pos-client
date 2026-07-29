<?php
namespace App\Module\Loyalty\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Loyalty\Enum\TransactionType;
use App\Module\Loyalty\Repository\LoyaltyTransactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoyaltyTransactionRepository::class)]
#[ORM\Table(name: 'loyalty_transaction')]
#[ORM\HasLifecycleCallbacks]
class LoyaltyTransaction
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LoyaltyCustomer::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false, onDelete: 'CASCADE')]
    private ?LoyaltyCustomer $customer = null;

    #[ORM\Column]
    private int $points = 0;

    #[ORM\Column(length: 16, enumType: TransactionType::class)]
    private TransactionType $type = TransactionType::Earn;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference = null;

    public function getId(): ?int { return $this->id; }

    public function getCustomer(): ?LoyaltyCustomer { return $this->customer; }
    public function setCustomer(?LoyaltyCustomer $v): static { $this->customer = $v; return $this; }

    public function getPoints(): int { return $this->points; }
    public function setPoints(int $v): static { $this->points = $v; return $this; }

    public function getType(): TransactionType { return $this->type; }
    public function setType(TransactionType $v): static { $this->type = $v; return $this; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $v): static { $this->reference = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'points'    => $this->points,
            'type'      => $this->type->value,
            'reference' => $this->reference,
            'createdAt' => $this->createdDate?->format('Y-m-d H:i:s'),
        ];
    }
}
