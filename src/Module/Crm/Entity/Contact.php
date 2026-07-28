<?php

namespace App\Module\Crm\Entity;

use App\Module\Core\Entity\SubEntity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Crm\Repository\ContactRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Contact implements SubEntity
{
    use EntityDateTimeAbleTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateOfBirth = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $gender = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $salutation = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $mobile = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $whatsapp = null;

    #[ORM\Column(length: 2)]
    private string $language = 'en';

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $receivesInvoice = false;

    #[ORM\Column]
    private bool $receivesTracking = false;

    #[ORM\Column]
    private bool $receivesArrival = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = $department;

        return $this;
    }

    public function getDateOfBirth(): ?\DateTimeInterface
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(?\DateTimeInterface $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;

        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getSalutation(): ?string
    {
        return $this->salutation;
    }

    public function setSalutation(?string $salutation): static
    {
        $this->salutation = $salutation;

        return $this;
    }

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(?string $mobile): static
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function getWhatsapp(): ?string
    {
        return $this->whatsapp;
    }

    public function setWhatsapp(?string $whatsapp): static
    {
        $this->whatsapp = $whatsapp;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isReceivesInvoice(): bool
    {
        return $this->receivesInvoice;
    }

    public function setReceivesInvoice(bool $receivesInvoice): static
    {
        $this->receivesInvoice = $receivesInvoice;

        return $this;
    }

    public function isReceivesTracking(): bool
    {
        return $this->receivesTracking;
    }

    public function setReceivesTracking(bool $receivesTracking): static
    {
        $this->receivesTracking = $receivesTracking;

        return $this;
    }

    public function isReceivesArrival(): bool
    {
        return $this->receivesArrival;
    }

    public function setReceivesArrival(bool $receivesArrival): static
    {
        $this->receivesArrival = $receivesArrival;

        return $this;
    }

    public function getFullName() {
        return implode(' ', [$this->firstName, $this->lastName]);
    }
}
