<?php
namespace App\Module\Notification\Entity;

use App\Module\Notification\Repository\NotificationTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationTemplateRepository::class)]
class NotificationTemplate
{
    #[ORM\Id]
    #[ORM\Column(name: 'key_col', length: 64)]
    private string $key = '';

    #[ORM\Column(length: 128)]
    private string $name = '';

    #[ORM\Column(length: 16)]
    private string $channel = 'EMAIL';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subjectTemplate = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $bodyTemplate = '';

    #[ORM\Column(length: 2)]
    private string $language = 'en';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $variables = null;

    public function getKey(): string { return $this->key; }
    public function setKey(string $v): static { $this->key = $v; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $v): static { $this->channel = $v; return $this; }
    public function getSubjectTemplate(): ?string { return $this->subjectTemplate; }
    public function setSubjectTemplate(?string $v): static { $this->subjectTemplate = $v; return $this; }
    public function getBodyTemplate(): string { return $this->bodyTemplate; }
    public function setBodyTemplate(string $v): static { $this->bodyTemplate = $v; return $this; }
    public function getLanguage(): string { return $this->language; }
    public function setLanguage(string $v): static { $this->language = $v; return $this; }
    public function getVariables(): ?array { return $this->variables; }
    public function setVariables(?array $v): static { $this->variables = $v; return $this; }
}
