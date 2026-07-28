<?php
namespace App\Module\Sales\Entity;

use App\Module\Sales\Enum\PaymentMethod;
use App\Module\Sales\Repository\OrderPaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderPaymentRepository::class)]
#[ORM\Table(name: 'order_payment')]
class OrderPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\Column(length: 16, enumType: PaymentMethod::class)]
    private PaymentMethod $method = PaymentMethod::Cash;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6)]
    private string $amount = '0.000000';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $paidAt;

    public function __construct()
    {
        $this->paidAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getOrder(): ?Order { return $this->order; }
    public function setOrder(?Order $v): static { $this->order = $v; return $this; }

    public function getMethod(): PaymentMethod { return $this->method; }
    public function setMethod(PaymentMethod $v): static { $this->method = $v; return $this; }

    public function getAmount(): string { return $this->amount; }
    public function setAmount(string|float $v): static { $this->amount = (string) $v; return $this; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $v): static { $this->reference = $v; return $this; }

    public function getPaidAt(): \DateTimeInterface { return $this->paidAt; }
    public function setPaidAt(\DateTimeInterface $v): static { $this->paidAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'method'    => $this->method->value,
            'amount'    => (float) $this->amount,
            'reference' => $this->reference,
            'paidAt'    => $this->paidAt->format('Y-m-d H:i:s'),
        ];
    }
}
