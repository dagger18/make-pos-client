<?php
namespace App\Module\Loyalty\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Loyalty\Repository\LoyaltyCustomerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoyaltyCustomerRepository::class)]
#[ORM\Table(name: 'loyalty_customer')]
#[ORM\HasLifecycleCallbacks]
class LoyaltyCustomer
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column]
    private int $points = 0;

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): static { $this->phone = $v ?: null; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): static { $this->email = $v ?: null; return $this; }

    public function getPoints(): int { return $this->points; }
    public function setPoints(int $v): static { $this->points = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'phone'     => $this->phone,
            'email'     => $this->email,
            'points'    => $this->points,
            'createdAt' => $this->createdDate?->format('Y-m-d H:i:s'),
        ];
    }
}
