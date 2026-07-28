# Notification & Alert System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a configurable notification system that watches shipment milestones, status changes, and financial deadlines, delivering alerts via in-app bell and email queue.

**Architecture:** Doctrine event subscriber fires async Messenger messages when tracked entities change; a handler evaluates active NotificationRules and writes InAppNotification + NotificationQueue records; a CLI command processes the email queue via the existing MailService; a second command handles deadline-based triggers on a cron schedule. The BO notification bell polls via existing `my-profile/get-notifications/{page}` endpoint wired to new API methods.

**Tech Stack:** PHP 8.2 / Symfony 7, Doctrine ORM (mysql + sqlite dual migrations), Symfony Messenger (async), Symfony Console commands, Vue 3 + Vuetify.

---

## Task 1: Core Entities + Repositories + Migrations

**Repo:** `d:\Projects\make-cargo-client`

### Checklist

- [ ] Create `src/Entity/InAppNotification.php`
- [ ] Create `src/Entity/NotificationRule.php`
- [ ] Create `src/Entity/NotificationTemplate.php`
- [ ] Create `src/Entity/NotificationQueue.php`
- [ ] Create `src/Entity/UserNotificationPreference.php`
- [ ] Create `src/Repository/InAppNotificationRepository.php`
- [ ] Create `src/Repository/NotificationRuleRepository.php`
- [ ] Create `src/Repository/NotificationTemplateRepository.php`
- [ ] Create `src/Repository/NotificationQueueRepository.php`
- [ ] Create `src/Repository/UserNotificationPreferenceRepository.php`
- [ ] Create `migrations/mysql/Version20260624190000.php` + `migrations/sqlite/Version20260624190000.php` — `in_app_notification`
- [ ] Create `migrations/mysql/Version20260624200000.php` + `migrations/sqlite/Version20260624200000.php` — `notification_rule`
- [ ] Create `migrations/mysql/Version20260624210000.php` + `migrations/sqlite/Version20260624210000.php` — `notification_template`
- [ ] Create `migrations/mysql/Version20260624220000.php` + `migrations/sqlite/Version20260624220000.php` — `notification_queue`
- [ ] Create `migrations/mysql/Version20260624230000.php` + `migrations/sqlite/Version20260624230000.php` — `user_notification_preference`
- [ ] Commit

### Entity: `src/Entity/InAppNotification.php`

```php
<?php
namespace App\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Repository\InAppNotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InAppNotificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class InAppNotification
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ruleKey = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column(length: 8)]
    private string $priority = 'NORMAL';

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $actionUrl = null;

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $v): static { $this->user = $v; return $this; }
    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $v): static { $this->shipment = $v; return $this; }
    public function getRuleKey(): ?string { return $this->ruleKey; }
    public function setRuleKey(?string $v): static { $this->ruleKey = $v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }
    public function getBody(): string { return $this->body; }
    public function setBody(string $v): static { $this->body = $v; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $v): static { $this->priority = $v; return $this; }
    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $v): static { $this->isRead = $v; return $this; }
    public function getReadAt(): ?\DateTimeInterface { return $this->readAt; }
    public function setReadAt(?\DateTimeInterface $v): static { $this->readAt = $v; return $this; }
    public function getActionUrl(): ?string { return $this->actionUrl; }
    public function setActionUrl(?string $v): static { $this->actionUrl = $v; return $this; }
}
```

### Entity: `src/Entity/NotificationRule.php`

```php
<?php
namespace App\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Repository\NotificationRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRuleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class NotificationRule
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $ruleKey = '';

    #[ORM\Column(length: 128)]
    private string $name = '';

    #[ORM\Column(length: 32)]
    private string $triggerType = ''; // DEADLINE / MILESTONE / STATUS_CHANGE / FINANCIAL

    #[ORM\Column(type: Types::JSON)]
    private array $triggerConfig = [];

    #[ORM\Column(type: Types::JSON)]
    private array $recipientConfig = [];

    #[ORM\Column(type: Types::JSON)]
    private array $channels = []; // ['EMAIL', 'IN_APP']

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $templateKey = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(length: 16)]
    private string $scopeType = 'GLOBAL';

    #[ORM\Column(length: 8)]
    private string $priority = 'NORMAL';

    public function getId(): ?int { return $this->id; }
    public function getRuleKey(): string { return $this->ruleKey; }
    public function setRuleKey(string $v): static { $this->ruleKey = $v; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function getTriggerType(): string { return $this->triggerType; }
    public function setTriggerType(string $v): static { $this->triggerType = $v; return $this; }
    public function getTriggerConfig(): array { return $this->triggerConfig; }
    public function setTriggerConfig(array $v): static { $this->triggerConfig = $v; return $this; }
    public function getRecipientConfig(): array { return $this->recipientConfig; }
    public function setRecipientConfig(array $v): static { $this->recipientConfig = $v; return $this; }
    public function getChannels(): array { return $this->channels; }
    public function setChannels(array $v): static { $this->channels = $v; return $this; }
    public function getTemplateKey(): ?string { return $this->templateKey; }
    public function setTemplateKey(?string $v): static { $this->templateKey = $v; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }
    public function getScopeType(): string { return $this->scopeType; }
    public function setScopeType(string $v): static { $this->scopeType = $v; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $v): static { $this->priority = $v; return $this; }
}
```

### Entity: `src/Entity/NotificationTemplate.php`

**Note:** The ORM field is `$key` but `key` is a MySQL reserved word — the actual DB column is `key_col` via `#[ORM\Column(name: 'key_col', length: 64)]`. The entity `#[ORM\Id]` uses `#[ORM\Column(name: 'key_col', ...)]` — no `#[ORM\GeneratedValue]` (string primary key).

```php
<?php
namespace App\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Repository\NotificationTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationTemplateRepository::class)]
#[ORM\HasLifecycleCallbacks]
class NotificationTemplate
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\Column(name: 'key_col', length: 64)]
    private string $key = '';

    #[ORM\Column(length: 128)]
    private string $name = '';

    #[ORM\Column(length: 16)]
    private string $channel = 'EMAIL'; // EMAIL / IN_APP

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
```

### Entity: `src/Entity/NotificationQueue.php`

```php
<?php
namespace App\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Repository\NotificationQueueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationQueueRepository::class)]
#[ORM\HasLifecycleCallbacks]
class NotificationQueue
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ruleKey = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 32)]
    private string $recipientType = 'USER'; // USER / CONTACT / EMAIL

    #[ORM\Column(nullable: true)]
    private ?int $recipientId = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $recipientEmail = null;

    #[ORM\Column(length: 16)]
    private string $channel = 'EMAIL';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column(length: 8)]
    private string $priority = 'NORMAL';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $scheduledAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(length: 16)]
    private string $status = 'PENDING'; // PENDING / SENT / FAILED / CANCELLED / SKIPPED

    #[ORM\Column]
    private int $attemptCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $providerRef = null;

    public function getId(): ?int { return $this->id; }
    public function getRuleKey(): ?string { return $this->ruleKey; }
    public function setRuleKey(?string $v): static { $this->ruleKey = $v; return $this; }
    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $v): static { $this->shipment = $v; return $this; }
    public function getRecipientType(): string { return $this->recipientType; }
    public function setRecipientType(string $v): static { $this->recipientType = $v; return $this; }
    public function getRecipientId(): ?int { return $this->recipientId; }
    public function setRecipientId(?int $v): static { $this->recipientId = $v; return $this; }
    public function getRecipientEmail(): ?string { return $this->recipientEmail; }
    public function setRecipientEmail(?string $v): static { $this->recipientEmail = $v; return $this; }
    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $v): static { $this->channel = $v; return $this; }
    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(?string $v): static { $this->subject = $v; return $this; }
    public function getBody(): string { return $this->body; }
    public function setBody(string $v): static { $this->body = $v; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $v): static { $this->priority = $v; return $this; }
    public function getScheduledAt(): \DateTimeInterface { return $this->scheduledAt; }
    public function setScheduledAt(\DateTimeInterface $v): static { $this->scheduledAt = $v; return $this; }
    public function getSentAt(): ?\DateTimeInterface { return $this->sentAt; }
    public function setSentAt(?\DateTimeInterface $v): static { $this->sentAt = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getAttemptCount(): int { return $this->attemptCount; }
    public function setAttemptCount(int $v): static { $this->attemptCount = $v; return $this; }
    public function getLastError(): ?string { return $this->lastError; }
    public function setLastError(?string $v): static { $this->lastError = $v; return $this; }
    public function getProviderRef(): ?string { return $this->providerRef; }
    public function setProviderRef(?string $v): static { $this->providerRef = $v; return $this; }
}
```

### Entity: `src/Entity/UserNotificationPreference.php`

**Note:** Composite primary key (user + ruleKey + channel) — no `EntityDateTimeAbleTrait`.

```php
<?php
namespace App\Entity;

use App\Repository\UserNotificationPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserNotificationPreferenceRepository::class)]
class UserNotificationPreference
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $ruleKey = '';

    #[ORM\Id]
    #[ORM\Column(length: 16)]
    private string $channel = '';

    #[ORM\Column]
    private bool $isEnabled = true;

    #[ORM\Column]
    private bool $digestMode = false;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $digestTime = null; // "08:00"

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $v): static { $this->user = $v; return $this; }
    public function getRuleKey(): string { return $this->ruleKey; }
    public function setRuleKey(string $v): static { $this->ruleKey = $v; return $this; }
    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $v): static { $this->channel = $v; return $this; }
    public function isEnabled(): bool { return $this->isEnabled; }
    public function setIsEnabled(bool $v): static { $this->isEnabled = $v; return $this; }
    public function isDigestMode(): bool { return $this->digestMode; }
    public function setDigestMode(bool $v): static { $this->digestMode = $v; return $this; }
    public function getDigestTime(): ?string { return $this->digestTime; }
    public function setDigestTime(?string $v): static { $this->digestTime = $v; return $this; }
}
```

### Repository: `src/Repository/InAppNotificationRepository.php`

```php
<?php
namespace App\Repository;

use App\Entity\InAppNotification;
use Doctrine\Persistence\ManagerRegistry;

class InAppNotificationRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry, ...$args)
    {
        parent::__construct($registry, ...$args);
    }

    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }

    public function findPagedForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $items = $this->createQueryBuilder('n')
            ->where('n.user = :uid')
            ->setParameter('uid', $userId)
            ->orderBy('n.createdDate', 'DESC')
            ->setMaxResults($perPage)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        $total = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'list'        => $items,
            'currentPage' => $page,
            'totalPages'  => (int) ceil($total / $perPage),
            'total'       => $total,
        ];
    }

    public function countUnreadForUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :uid')
            ->andWhere('n.isRead = false')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markReadForUser(int $userId, ?array $ids = null): void
    {
        $qb = $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.readAt', ':now')
            ->where('n.user = :uid')
            ->andWhere('n.isRead = false')
            ->setParameter('uid', $userId)
            ->setParameter('now', new \DateTime());

        if ($ids !== null) {
            $qb->andWhere('n.id IN (:ids)')->setParameter('ids', $ids);
        }

        $qb->getQuery()->execute();
    }
}
```

### Repository: `src/Repository/NotificationRuleRepository.php`

```php
<?php
namespace App\Repository;

use App\Entity\NotificationRule;
use Doctrine\Persistence\ManagerRegistry;

class NotificationRuleRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry, ...$args)
    {
        parent::__construct($registry, ...$args);
    }

    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }

    public function findActiveByTriggerType(string $triggerType): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.triggerType = :type')
            ->andWhere('r.isActive = true')
            ->setParameter('type', $triggerType)
            ->getQuery()
            ->getResult();
    }

    public function findActiveDeadlineRules(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.triggerType = :type')
            ->andWhere('r.isActive = true')
            ->setParameter('type', 'DEADLINE')
            ->getQuery()
            ->getResult();
    }

    public function findActiveFinancialRules(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.triggerType = :type')
            ->andWhere('r.isActive = true')
            ->setParameter('type', 'FINANCIAL')
            ->getQuery()
            ->getResult();
    }
}
```

### Repository: `src/Repository/NotificationTemplateRepository.php`

```php
<?php
namespace App\Repository;

use App\Entity\NotificationTemplate;
use Doctrine\Persistence\ManagerRegistry;

class NotificationTemplateRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry, ...$args)
    {
        parent::__construct($registry, ...$args);
    }

    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }
}
```

### Repository: `src/Repository/NotificationQueueRepository.php`

```php
<?php
namespace App\Repository;

use App\Entity\NotificationQueue;
use Doctrine\Persistence\ManagerRegistry;

class NotificationQueueRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry, ...$args)
    {
        parent::__construct($registry, ...$args);
    }

    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }

    public function findPendingDue(int $limit = 50): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.status = :status')
            ->andWhere('q.scheduledAt <= :now')
            ->setParameter('status', 'PENDING')
            ->setParameter('now', new \DateTime())
            ->orderBy('q.priority', 'DESC')
            ->addOrderBy('q.scheduledAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
```

### Repository: `src/Repository/UserNotificationPreferenceRepository.php`

```php
<?php
namespace App\Repository;

use App\Entity\User;
use App\Entity\UserNotificationPreference;
use Doctrine\Persistence\ManagerRegistry;

class UserNotificationPreferenceRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry, ...$args)
    {
        parent::__construct($registry, ...$args);
    }

    public function save($entity, ?\Symfony\Component\HttpFoundation\Request $request = null)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }

    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function savePreference(UserNotificationPreference $entity): UserNotificationPreference
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
        return $entity;
    }
}
```

### Migration: `migrations/mysql/Version20260624190000.php` — `in_app_notification`

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624190000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create in_app_notification table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE in_app_notification (id INT NOT NULL AUTO_INCREMENT, user_id INT NOT NULL, shipment_id INT DEFAULT NULL, rule_key VARCHAR(64) DEFAULT NULL, title VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, priority VARCHAR(8) NOT NULL DEFAULT \'NORMAL\', is_read TINYINT(1) NOT NULL DEFAULT 0, read_at DATETIME DEFAULT NULL, action_url LONGTEXT DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, PRIMARY KEY (id), INDEX IDX_ian_user_unread (user_id, is_read), CONSTRAINT FK_ian_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE, CONSTRAINT FK_ian_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE in_app_notification');
    }
}
```

### Migration: `migrations/sqlite/Version20260624190000.php` — `in_app_notification`

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624190000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create in_app_notification table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE in_app_notification (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, shipment_id INTEGER DEFAULT NULL, rule_key VARCHAR(64) DEFAULT NULL, title VARCHAR(255) NOT NULL, body CLOB NOT NULL, priority VARCHAR(8) NOT NULL DEFAULT \'NORMAL\', is_read INTEGER NOT NULL DEFAULT 0, read_at DATETIME DEFAULT NULL, action_url CLOB DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, CONSTRAINT FK_ian_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_ian_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_ian_user_unread ON in_app_notification (user_id, is_read)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE in_app_notification');
    }
}
```

### Migration: `migrations/mysql/Version20260624200000.php` — `notification_rule`

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624200000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create notification_rule table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_rule (id INT NOT NULL AUTO_INCREMENT, rule_key VARCHAR(64) NOT NULL, name VARCHAR(128) NOT NULL, trigger_type VARCHAR(32) NOT NULL, trigger_config JSON NOT NULL, recipient_config JSON NOT NULL, channels JSON NOT NULL, template_key VARCHAR(64) DEFAULT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, scope_type VARCHAR(16) NOT NULL DEFAULT \'GLOBAL\', priority VARCHAR(8) NOT NULL DEFAULT \'NORMAL\', created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, PRIMARY KEY (id), UNIQUE INDEX UNIQ_nr_key (rule_key)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_rule');
    }
}
```

### Migration: `migrations/sqlite/Version20260624200000.php` — `notification_rule`

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624200000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create notification_rule table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_rule (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, rule_key VARCHAR(64) NOT NULL, name VARCHAR(128) NOT NULL, trigger_type VARCHAR(32) NOT NULL, trigger_config CLOB NOT NULL, recipient_config CLOB NOT NULL, channels CLOB NOT NULL, template_key VARCHAR(64) DEFAULT NULL, is_active INTEGER NOT NULL DEFAULT 1, scope_type VARCHAR(16) NOT NULL DEFAULT \'GLOBAL\', priority VARCHAR(8) NOT NULL DEFAULT \'NORMAL\', created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_nr_key ON notification_rule (rule_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_rule');
    }
}
```

### Migration: `migrations/mysql/Version20260624210000.php` — `notification_template`

**Note:** ORM field `$key` maps to column `key_col` to avoid MySQL reserved word conflict.

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624210000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create notification_template table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_template (key_col VARCHAR(64) NOT NULL, name VARCHAR(128) NOT NULL, channel VARCHAR(16) NOT NULL DEFAULT \'EMAIL\', subject_template VARCHAR(255) DEFAULT NULL, body_template LONGTEXT NOT NULL, language CHAR(2) NOT NULL DEFAULT \'en\', variables JSON DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, PRIMARY KEY (key_col)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_template');
    }
}
```

### Migration: `migrations/sqlite/Version20260624210000.php` — `notification_template`

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624210000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create notification_template table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_template (key_col VARCHAR(64) NOT NULL, name VARCHAR(128) NOT NULL, channel VARCHAR(16) NOT NULL DEFAULT \'EMAIL\', subject_template VARCHAR(255) DEFAULT NULL, body_template CLOB NOT NULL, language VARCHAR(2) NOT NULL DEFAULT \'en\', variables CLOB DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, PRIMARY KEY (key_col))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_template');
    }
}
```

### Migration: `migrations/mysql/Version20260624220000.php` — `notification_queue`

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624220000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create notification_queue table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_queue (id INT NOT NULL AUTO_INCREMENT, shipment_id INT DEFAULT NULL, rule_key VARCHAR(64) DEFAULT NULL, recipient_type VARCHAR(32) NOT NULL DEFAULT \'USER\', recipient_id INT DEFAULT NULL, recipient_email VARCHAR(128) DEFAULT NULL, channel VARCHAR(16) NOT NULL DEFAULT \'EMAIL\', subject VARCHAR(255) DEFAULT NULL, body LONGTEXT NOT NULL, priority VARCHAR(8) NOT NULL DEFAULT \'NORMAL\', scheduled_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, status VARCHAR(16) NOT NULL DEFAULT \'PENDING\', attempt_count INT NOT NULL DEFAULT 0, last_error LONGTEXT DEFAULT NULL, provider_ref VARCHAR(128) DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, PRIMARY KEY (id), INDEX IDX_nq_status_scheduled (status, scheduled_at), CONSTRAINT FK_nq_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_queue');
    }
}
```

### Migration: `migrations/sqlite/Version20260624220000.php` — `notification_queue`

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624220000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create notification_queue table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_queue (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, shipment_id INTEGER DEFAULT NULL, rule_key VARCHAR(64) DEFAULT NULL, recipient_type VARCHAR(32) NOT NULL DEFAULT \'USER\', recipient_id INTEGER DEFAULT NULL, recipient_email VARCHAR(128) DEFAULT NULL, channel VARCHAR(16) NOT NULL DEFAULT \'EMAIL\', subject VARCHAR(255) DEFAULT NULL, body CLOB NOT NULL, priority VARCHAR(8) NOT NULL DEFAULT \'NORMAL\', scheduled_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, status VARCHAR(16) NOT NULL DEFAULT \'PENDING\', attempt_count INTEGER NOT NULL DEFAULT 0, last_error CLOB DEFAULT NULL, provider_ref VARCHAR(128) DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME DEFAULT NULL, CONSTRAINT FK_nq_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_nq_status_scheduled ON notification_queue (status, scheduled_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_queue');
    }
}
```

### Migration: `migrations/mysql/Version20260624230000.php` — `user_notification_preference`

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624230000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create user_notification_preference table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_notification_preference (user_id INT NOT NULL, rule_key VARCHAR(64) NOT NULL, channel VARCHAR(16) NOT NULL, is_enabled TINYINT(1) NOT NULL DEFAULT 1, digest_mode TINYINT(1) NOT NULL DEFAULT 0, digest_time VARCHAR(5) DEFAULT NULL, PRIMARY KEY (user_id, rule_key, channel), CONSTRAINT FK_unp_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_notification_preference');
    }
}
```

### Migration: `migrations/sqlite/Version20260624230000.php` — `user_notification_preference`

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624230000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create user_notification_preference table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_notification_preference (user_id INTEGER NOT NULL, rule_key VARCHAR(64) NOT NULL, channel VARCHAR(16) NOT NULL, is_enabled INTEGER NOT NULL DEFAULT 1, digest_mode INTEGER NOT NULL DEFAULT 0, digest_time VARCHAR(5) DEFAULT NULL, PRIMARY KEY (user_id, rule_key, channel), CONSTRAINT FK_unp_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_notification_preference');
    }
}
```

### Commit

```bash
# in d:\Projects\make-cargo-client
git add src/Entity/InAppNotification.php src/Entity/NotificationRule.php src/Entity/NotificationTemplate.php src/Entity/NotificationQueue.php src/Entity/UserNotificationPreference.php
git add src/Repository/InAppNotificationRepository.php src/Repository/NotificationRuleRepository.php src/Repository/NotificationTemplateRepository.php src/Repository/NotificationQueueRepository.php src/Repository/UserNotificationPreferenceRepository.php
git add migrations/mysql/Version20260624190000.php migrations/mysql/Version20260624200000.php migrations/mysql/Version20260624210000.php migrations/mysql/Version20260624220000.php migrations/mysql/Version20260624230000.php
git add migrations/sqlite/Version20260624190000.php migrations/sqlite/Version20260624200000.php migrations/sqlite/Version20260624210000.php migrations/sqlite/Version20260624220000.php migrations/sqlite/Version20260624230000.php
git commit -m "feat: add notification system entities and migrations"
```

---

## Task 2: In-App Notification Service + API Endpoints

**Repo:** `d:\Projects\make-cargo-client`

### Checklist

- [ ] Create `src/Service/InAppNotificationService.php`
- [ ] Modify `src/Controller/Api/MyProfileController.php` — add `getNotifications()`, `markNotificationsRead()`, update `ping()`
- [ ] Modify `src/Service/MailService.php` — add `sendRaw()` method
- [ ] Modify `config/services.yaml` — add `InAppNotificationService` to `app.auto_service_locator`
- [ ] Commit

### `src/Service/InAppNotificationService.php`

This service does **not** extend `BaseService` (no user context needed in background jobs). It is a standalone service.

```php
<?php
namespace App\Service;

use App\Entity\InAppNotification;
use App\Entity\Shipment;
use App\Entity\User;
use App\Repository\InAppNotificationRepository;

class InAppNotificationService
{
    public function __construct(
        private readonly InAppNotificationRepository $repository,
    ) {}

    public function create(
        User    $user,
        string  $title,
        string  $body,
        string  $priority = 'NORMAL',
        ?Shipment $shipment = null,
        ?string $ruleKey = null,
        ?string $actionUrl = null,
    ): InAppNotification {
        $n = new InAppNotification();
        $n->setUser($user);
        $n->setTitle($title);
        $n->setBody($body);
        $n->setPriority($priority);
        $n->setShipment($shipment);
        $n->setRuleKey($ruleKey);
        $n->setActionUrl($actionUrl);
        return $this->repository->save($n);
    }

    public function getPagedForUser(int $userId, int $page): array
    {
        return $this->repository->findPagedForUser($userId, $page);
    }

    public function markRead(int $userId, ?array $ids = null): void
    {
        $this->repository->markReadForUser($userId, $ids);
    }

    public function countUnread(int $userId): int
    {
        return $this->repository->countUnreadForUser($userId);
    }
}
```

### Modify `src/Controller/Api/MyProfileController.php`

Add the following imports at the top:

```php
use App\Service\InAppNotificationService;
use Symfony\Component\HttpFoundation\Response;
```

Replace the existing `ping()` method and add two new methods:

```php
#[Route('/ping', methods: ['GET'])]
public function ping(
    UserService $userService,
    InAppNotificationService $notificationService,
    #[CurrentUser] User $user,
): JsonResponse {
    $pingResult = $userService->ping();
    $pingResult['notification'] = $notificationService->countUnread($user->getId());
    return $this->json($pingResult, Response::HTTP_CREATED);
}

#[Route('/get-notifications/{page}', methods: ['GET'])]
public function getNotifications(
    int $page,
    #[CurrentUser] User $user,
    InAppNotificationService $notificationService,
): JsonResponse {
    $data = $notificationService->getPagedForUser($user->getId(), max(1, $page));
    return $this->json([
        'currentPage' => $data['currentPage'],
        'totalPages'  => $data['totalPages'],
        'total'       => $data['total'],
        'list'        => array_map(fn($n) => [
            'id'         => $n->getId(),
            'title'      => $n->getTitle(),
            'body'       => $n->getBody(),
            'priority'   => $n->getPriority(),
            'isRead'     => $n->isRead(),
            'actionUrl'  => $n->getActionUrl(),
            'shipmentId' => $n->getShipment()?->getId(),
            'shipment'   => $n->getShipment() ? ['code' => $n->getShipment()->getCode()] : null,
            'ruleKey'    => $n->getRuleKey(),
            'createdDate'=> $n->getCreatedDate()?->format(\DateTimeInterface::ATOM),
        ], $data['list']),
    ]);
}

#[Route('/mark-notifications-read', methods: ['POST'])]
public function markNotificationsRead(
    Request $request,
    #[CurrentUser] User $user,
    InAppNotificationService $notificationService,
): JsonResponse {
    $body = json_decode($request->getContent(), true) ?? [];
    $ids = isset($body['ids']) && is_array($body['ids'])
        ? array_map('intval', $body['ids'])
        : null;
    $notificationService->markRead($user->getId(), $ids);
    return $this->json(null, Response::HTTP_NO_CONTENT);
}
```

**Important:** Read `MyProfileController.php` before editing. The existing `ping()` method at line 38 must be replaced (not duplicated). The existing import `use Symfony\Component\HttpFoundation\Response;` may already be present — check before adding.

### Modify `src/Service/MailService.php` — add `sendRaw()`

Add this method after the existing `send()` method:

```php
public function sendRaw(string $to, string $subject, string $htmlBody): void
{
    $email = (new \Symfony\Component\Mime\Email())
        ->from(new Address($this->defaultFromAddress, 'MakeCargo Team'))
        ->to($to)
        ->subject($subject)
        ->html($htmlBody);
    $this->brevoFallbackTransport->send($email);
}
```

### Modify `config/services.yaml` — add to `app.auto_service_locator`

Add after the last `App\Service\` entry in the locator arguments block:

```yaml
                App\Service\InAppNotificationService: '@App\Service\InAppNotificationService'
```

### Commit

```bash
git add src/Service/InAppNotificationService.php src/Controller/Api/MyProfileController.php src/Service/MailService.php config/services.yaml
git commit -m "feat: add in-app notification service, API endpoints, and ping count"
```

---

## Task 3: Notification Generator + Event Integration

**Repo:** `d:\Projects\make-cargo-client`

### Checklist

- [ ] Create `src/Service/NotificationTemplateRenderer.php`
- [ ] Create `src/Service/NotificationGeneratorService.php`
- [ ] Create `src/Message/NotificationTriggerMessage.php`
- [ ] Create `src/MessageHandler/NotificationTriggerMessageHandler.php`
- [ ] Create `src/EventListener/NotificationEventListener.php`
- [ ] Modify `config/packages/messenger.yaml` — add routing entry
- [ ] Commit

### `src/Service/NotificationTemplateRenderer.php`

Renders `{{ variable }}` and `{{variable}}` placeholders against a `NotificationTemplate` body/subject. Falls back gracefully if template not found.

```php
<?php
namespace App\Service;

use App\Repository\NotificationTemplateRepository;

class NotificationTemplateRenderer
{
    public function __construct(
        private readonly NotificationTemplateRepository $templateRepository,
    ) {}

    public function render(string $templateKey, array $variables): array
    {
        $template = $this->templateRepository->findOneBy(['key' => $templateKey]);
        if (!$template) {
            return [
                'subject' => $templateKey,
                'body'    => implode(', ', array_map(
                    fn($k, $v) => "$k: $v",
                    array_keys($variables),
                    $variables
                )),
            ];
        }
        return [
            'subject' => $this->replace($template->getSubjectTemplate() ?? '', $variables),
            'body'    => $this->replace($template->getBodyTemplate(), $variables),
        ];
    }

    private function replace(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{ ' . $key . ' }}', (string) $value, $template);
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }
}
```

**Note on template lookup:** `findOneBy(['key' => $templateKey])` uses the PHP property name `key`, which Doctrine maps to column `key_col`. This is correct — Doctrine DQL uses the entity property name, not the column name.

### `src/Message/NotificationTriggerMessage.php`

```php
<?php
namespace App\Message;

class NotificationTriggerMessage
{
    public function __construct(
        public readonly string $eventType,  // 'milestone.created' | 'shipment.status_changed'
        public readonly int    $entityId,
        public readonly array  $context = [],
    ) {}
}
```

### `src/Service/NotificationGeneratorService.php`

Standalone service (no `BaseService`). Evaluates active rules and creates `InAppNotification` + `NotificationQueue` records.

**Important note about `ShipmentMilestone::getMilestoneCode()`:** This method returns `?MilestoneCode` (nullable), so call `.value` only after a null check. The `customerLabel()` method exists on `MilestoneCode`.

**Important note about `Shipment::getCreatedBy()`:** This method exists and returns `?User`. The `User::getEmail()` method returns the email string.

```php
<?php
namespace App\Service;

use App\Entity\NotificationQueue;
use App\Entity\Shipment;
use App\Entity\ShipmentMilestone;
use App\Entity\User;
use App\Repository\NotificationQueueRepository;
use App\Repository\NotificationRuleRepository;
use App\Repository\UserRepository;

class NotificationGeneratorService
{
    public function __construct(
        private readonly NotificationRuleRepository   $ruleRepository,
        private readonly InAppNotificationService     $inAppService,
        private readonly NotificationQueueRepository  $queueRepository,
        private readonly NotificationTemplateRenderer $renderer,
        private readonly UserRepository               $userRepository,
    ) {}

    public function handleMilestone(ShipmentMilestone $milestone): void
    {
        $milestoneCode = $milestone->getMilestoneCode()?->value;
        if (!$milestoneCode) return;

        $shipment = $milestone->getShipment();
        if (!$shipment) return;

        $rules = $this->ruleRepository->findActiveByTriggerType('MILESTONE');
        foreach ($rules as $rule) {
            $cfg = $rule->getTriggerConfig();
            if (isset($cfg['milestone_code']) && $cfg['milestone_code'] !== $milestoneCode) {
                continue;
            }

            $vars = [
                'shipment_code'   => $shipment->getCode() ?? '',
                'milestone_code'  => $milestoneCode,
                'milestone_label' => $milestone->getMilestoneCode()->customerLabel(),
                'actual_date'     => $milestone->getActualDate()?->format('Y-m-d') ?? '',
            ];
            $rendered = $rule->getTemplateKey()
                ? $this->renderer->render($rule->getTemplateKey(), $vars)
                : ['subject' => "Milestone: {$milestoneCode}", 'body' => "Shipment {$vars['shipment_code']}: {$vars['milestone_label']}"];

            $this->dispatchToRecipients($rule, $shipment, $rendered['subject'], $rendered['body']);
        }
    }

    public function handleStatusChange(Shipment $shipment, string $oldStatus, string $newStatus): void
    {
        $rules = $this->ruleRepository->findActiveByTriggerType('STATUS_CHANGE');
        foreach ($rules as $rule) {
            $cfg = $rule->getTriggerConfig();
            if (isset($cfg['new_status']) && $cfg['new_status'] !== $newStatus) {
                continue;
            }
            $vars = [
                'shipment_code' => $shipment->getCode() ?? '',
                'old_status'    => $oldStatus,
                'new_status'    => $newStatus,
            ];
            $rendered = $rule->getTemplateKey()
                ? $this->renderer->render($rule->getTemplateKey(), $vars)
                : ['subject' => "Status changed: {$newStatus}", 'body' => "Shipment {$vars['shipment_code']} status changed from {$oldStatus} to {$newStatus}"];

            $this->dispatchToRecipients($rule, $shipment, $rendered['subject'], $rendered['body']);
        }
    }

    private function dispatchToRecipients(object $rule, Shipment $shipment, string $subject, string $body): void
    {
        $operator = $shipment->getCreatedBy();
        foreach ($rule->getRecipientConfig() as $recipientDef) {
            $type = $recipientDef['type'] ?? '';
            if ($type === 'JOB_OPERATOR' && $operator) {
                $this->dispatchToUser($rule, $operator, $shipment, $subject, $body);
            }
            if ($type === 'FIXED_EMAIL' && !empty($recipientDef['email'])) {
                $this->enqueueEmail($rule, $shipment, $recipientDef['email'], $subject, $body);
            }
        }
    }

    private function dispatchToUser(object $rule, User $user, Shipment $shipment, string $subject, string $body): void
    {
        foreach ($rule->getChannels() as $channel) {
            if ($channel === 'IN_APP') {
                $this->inAppService->create($user, $subject, $body, $rule->getPriority(), $shipment, $rule->getRuleKey());
            }
            if ($channel === 'EMAIL' && $user->getEmail()) {
                $this->enqueueEmail($rule, $shipment, $user->getEmail(), $subject, $body);
            }
        }
    }

    private function enqueueEmail(object $rule, Shipment $shipment, string $email, string $subject, string $body): void
    {
        $q = new NotificationQueue();
        $q->setRuleKey($rule->getRuleKey());
        $q->setShipment($shipment);
        $q->setRecipientType('EMAIL');
        $q->setRecipientEmail($email);
        $q->setChannel('EMAIL');
        $q->setSubject($subject);
        $q->setBody($body);
        $q->setPriority($rule->getPriority());
        $q->setScheduledAt(new \DateTime());
        $this->queueRepository->save($q);
    }
}
```

### `src/MessageHandler/NotificationTriggerMessageHandler.php`

```php
<?php
namespace App\MessageHandler;

use App\Message\NotificationTriggerMessage;
use App\Repository\ShipmentMilestoneRepository;
use App\Repository\ShipmentRepository;
use App\Service\NotificationGeneratorService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class NotificationTriggerMessageHandler
{
    public function __construct(
        private readonly NotificationGeneratorService $generator,
        private readonly ShipmentMilestoneRepository  $milestoneRepository,
        private readonly ShipmentRepository           $shipmentRepository,
    ) {}

    public function __invoke(NotificationTriggerMessage $message): void
    {
        match ($message->eventType) {
            'milestone.created'       => $this->handleMilestone($message->entityId),
            'shipment.status_changed' => $this->handleStatusChange($message->entityId, $message->context),
            default                   => null,
        };
    }

    private function handleMilestone(int $id): void
    {
        $milestone = $this->milestoneRepository->find($id);
        if ($milestone) {
            $this->generator->handleMilestone($milestone);
        }
    }

    private function handleStatusChange(int $id, array $context): void
    {
        $shipment = $this->shipmentRepository->find($id);
        if ($shipment) {
            $this->generator->handleStatusChange(
                $shipment,
                $context['oldStatus'] ?? '',
                $context['newStatus'] ?? ''
            );
        }
    }
}
```

### `src/EventListener/NotificationEventListener.php`

Listens to Doctrine `postPersist` (new `ShipmentMilestone`) and `postUpdate` (`Shipment.status` change). Dispatches async Messenger message — does not block the request.

**Important about `Shipment::$status`:** The field is `?ShipmentStatus` (an enum). The change set values are enum instances. Extract `.value` safely: `$oldStatus?->value ?? (string) $oldStatus`.

```php
<?php
namespace App\EventListener;

use App\Entity\Shipment;
use App\Entity\ShipmentMilestone;
use App\Message\NotificationTriggerMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
class NotificationEventListener
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof ShipmentMilestone && $entity->getId()) {
            $this->bus->dispatch(new NotificationTriggerMessage(
                'milestone.created',
                $entity->getId()
            ));
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!($entity instanceof Shipment)) {
            return;
        }
        $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);
        if (!isset($changeSet['status'])) {
            return;
        }
        [$oldStatus, $newStatus] = $changeSet['status'];
        $this->bus->dispatch(new NotificationTriggerMessage(
            'shipment.status_changed',
            $entity->getId(),
            [
                'oldStatus' => $oldStatus?->value ?? (string) $oldStatus,
                'newStatus' => $newStatus?->value ?? (string) $newStatus,
            ]
        ));
    }
}
```

### Modify `config/packages/messenger.yaml`

Add to the `routing:` block (after the existing entries):

```yaml
            App\Message\NotificationTriggerMessage: async
```

### Commit

```bash
git add src/Service/NotificationTemplateRenderer.php src/Service/NotificationGeneratorService.php
git add src/Message/NotificationTriggerMessage.php src/MessageHandler/NotificationTriggerMessageHandler.php
git add src/EventListener/NotificationEventListener.php config/packages/messenger.yaml
git commit -m "feat: add notification generator, event listener, and Messenger trigger"
```

---

## Task 4: Email Queue Processor Command

**Repo:** `d:\Projects\make-cargo-client`

### Checklist

- [ ] Create `src/Command/NotificationQueueProcessorCommand.php`
- [ ] Commit

### `src/Command/NotificationQueueProcessorCommand.php`

Processes up to 50 `PENDING` `NotificationQueue` records whose `scheduledAt <= now`. Calls `MailService::sendRaw()`. Retries up to 3 times before marking `FAILED`.

```php
<?php
namespace App\Command;

use App\Repository\NotificationQueueRepository;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:notifications:process-queue',
    description: 'Process pending notification email queue'
)]
class NotificationQueueProcessorCommand extends Command
{
    public function __construct(
        private readonly NotificationQueueRepository $queueRepository,
        private readonly MailService                 $mailService,
        private readonly EntityManagerInterface      $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pending = $this->queueRepository->findPendingDue(50);
        $output->writeln(sprintf('Processing %d pending notifications', count($pending)));

        foreach ($pending as $item) {
            $item->setAttemptCount($item->getAttemptCount() + 1);
            try {
                if ($item->getChannel() === 'EMAIL' && $item->getRecipientEmail()) {
                    $this->mailService->sendRaw(
                        $item->getRecipientEmail(),
                        $item->getSubject() ?? 'Notification',
                        $item->getBody(),
                    );
                    $item->setStatus('SENT');
                    $item->setSentAt(new \DateTime());
                }
            } catch (\Throwable $e) {
                $item->setLastError($e->getMessage());
                $item->setStatus($item->getAttemptCount() >= 3 ? 'FAILED' : 'PENDING');
            }
            $this->em->flush();
        }

        $output->writeln('Done.');
        return Command::SUCCESS;
    }
}
```

**Note on `MailService` in a console command context:** `MailService` extends `BaseService` and calls `$this->reflectFromParent($baseService)` in the constructor. When injected into a command, `BaseService` will be available via DI. However, since we're calling `sendRaw()` (which doesn't use user context), this is safe. The command uses `MailService` only for its transport-level method.

### Commit

```bash
git add src/Command/NotificationQueueProcessorCommand.php
git commit -m "feat: add notification queue email processor command"
```

---

## Task 5: Deadline Scheduler Command

**Repo:** `d:\Projects\make-cargo-client`

### Checklist

- [ ] Read `src/Entity/Booking.php` to verify the SI cutoff field name before implementing
- [ ] Create `src/Command/NotificationSchedulerCommand.php`
- [ ] Commit

### Pre-implementation check

**Read `src/Entity/Booking.php` before implementing.** The actual SI cutoff field is `$siCutOff` with getter `getSiCutOff()` (not `getCutoffSi()` as assumed in spec). Adjust the query builder join condition accordingly:

```php
->innerJoin('s.booking', 'b')
->where('b.siCutOff IS NOT NULL')     // DQL uses property name, not column name
->andWhere('b.siCutOff > :start')
->andWhere('b.siCutOff <= :end')
```

And in the vars array:
```php
'cutoff_si' => $shipment->getBooking()?->getSiCutOff()?->format('Y-m-d H:i') ?? '',
```

### `src/Command/NotificationSchedulerCommand.php`

Runs on a cron (every hour). Processes `DEADLINE` rules (SI cutoff) and `FINANCIAL` rules (invoice overdue). Creates `InAppNotification` + `NotificationQueue` records directly.

```php
<?php
namespace App\Command;

use App\Entity\NotificationQueue;
use App\Misc\Enum\EbitNoteType;
use App\Repository\EbitNoteRepository;
use App\Repository\NotificationQueueRepository;
use App\Repository\NotificationRuleRepository;
use App\Repository\ShipmentRepository;
use App\Service\InAppNotificationService;
use App\Service\NotificationTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:notifications:schedule-deadlines',
    description: 'Generate deadline and financial alert notifications'
)]
class NotificationSchedulerCommand extends Command
{
    public function __construct(
        private readonly NotificationRuleRepository   $ruleRepository,
        private readonly ShipmentRepository           $shipmentRepository,
        private readonly EbitNoteRepository           $ebitNoteRepository,
        private readonly InAppNotificationService     $inAppService,
        private readonly NotificationQueueRepository  $queueRepository,
        private readonly NotificationTemplateRenderer $renderer,
        private readonly EntityManagerInterface       $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->processDeadlineRules($output);
        $this->processFinancialRules($output);
        return Command::SUCCESS;
    }

    private function processDeadlineRules(OutputInterface $output): void
    {
        $rules = $this->ruleRepository->findActiveDeadlineRules();
        foreach ($rules as $rule) {
            $cfg         = $rule->getTriggerConfig();
            $field       = $cfg['deadline_field'] ?? null;
            $hoursBefore = (int) ($cfg['hours_before'] ?? 48);

            if ($field !== 'booking.cutoff_si') continue;

            $windowStart = new \DateTime();
            $windowEnd   = (new \DateTime())->modify("+{$hoursBefore} hours");

            // Use property name 'siCutOff' in DQL (maps to si_cut_off column)
            $shipments = $this->shipmentRepository->createQueryBuilder('s')
                ->innerJoin('s.booking', 'b')
                ->where('b.siCutOff IS NOT NULL')
                ->andWhere('b.siCutOff > :start')
                ->andWhere('b.siCutOff <= :end')
                ->setParameter('start', $windowStart)
                ->setParameter('end', $windowEnd)
                ->getQuery()
                ->getResult();

            foreach ($shipments as $shipment) {
                $operator = $shipment->getCreatedBy();
                if (!$operator) continue;

                $vars = [
                    'shipment_code'   => $shipment->getCode() ?? '',
                    'hours_remaining' => $hoursBefore,
                    'cutoff_si'       => $shipment->getBooking()?->getSiCutOff()?->format('Y-m-d H:i') ?? '',
                ];
                $rendered = $rule->getTemplateKey()
                    ? $this->renderer->render($rule->getTemplateKey(), $vars)
                    : ['subject' => "SI Cutoff in {$hoursBefore}h — {$vars['shipment_code']}", 'body' => "SI cutoff for {$vars['shipment_code']} is at {$vars['cutoff_si']}"];

                foreach ($rule->getChannels() as $channel) {
                    if ($channel === 'IN_APP') {
                        $this->inAppService->create($operator, $rendered['subject'], $rendered['body'], $rule->getPriority(), $shipment, $rule->getRuleKey());
                    }
                    if ($channel === 'EMAIL' && $operator->getEmail()) {
                        $q = (new NotificationQueue())
                            ->setRuleKey($rule->getRuleKey())
                            ->setShipment($shipment)
                            ->setRecipientType('USER')
                            ->setRecipientEmail($operator->getEmail())
                            ->setChannel('EMAIL')
                            ->setSubject($rendered['subject'])
                            ->setBody($rendered['body'])
                            ->setPriority($rule->getPriority())
                            ->setScheduledAt(new \DateTime());
                        $this->queueRepository->save($q);
                    }
                }
            }
            $output->writeln("Deadline rule {$rule->getRuleKey()}: processed " . count($shipments) . " shipments");
        }
    }

    private function processFinancialRules(OutputInterface $output): void
    {
        $rules = $this->ruleRepository->findActiveFinancialRules();
        foreach ($rules as $rule) {
            $cfg         = $rule->getTriggerConfig();
            $event       = $cfg['event'] ?? '';
            $daysOverdue = (int) ($cfg['days_overdue'] ?? 7);

            if ($event !== 'INVOICE_OVERDUE') continue;

            $dueDate = (new \DateTime())->modify("-{$daysOverdue} days");

            $invoices = $this->ebitNoteRepository->createQueryBuilder('e')
                ->where('e.type = :type')
                ->andWhere('e.createdDate <= :dueDate')
                ->andWhere('e.status NOT IN (:paidStatuses)')
                ->setParameter('type', EbitNoteType::InvoiceDebit)
                ->setParameter('dueDate', $dueDate)
                ->setParameter('paidStatuses', ['P', 'D'])
                ->getQuery()
                ->getResult();

            foreach ($invoices as $invoice) {
                $shipment = $invoice->getShipment();
                $operator = $shipment?->getCreatedBy();
                if (!$operator) continue;

                $vars = [
                    'shipment_code' => $shipment->getCode() ?? '',
                    'invoice_code'  => $invoice->getCode() ?? '',
                    'days_overdue'  => $daysOverdue,
                ];
                $rendered = $rule->getTemplateKey()
                    ? $this->renderer->render($rule->getTemplateKey(), $vars)
                    : ['subject' => "Invoice overdue {$daysOverdue}d — {$vars['invoice_code']}", 'body' => "Invoice {$vars['invoice_code']} for shipment {$vars['shipment_code']} is {$daysOverdue} days overdue."];

                foreach ($rule->getChannels() as $channel) {
                    if ($channel === 'IN_APP') {
                        $this->inAppService->create($operator, $rendered['subject'], $rendered['body'], $rule->getPriority(), $shipment, $rule->getRuleKey());
                    }
                    if ($channel === 'EMAIL' && $operator->getEmail()) {
                        $q = (new NotificationQueue())
                            ->setRuleKey($rule->getRuleKey())
                            ->setShipment($shipment)
                            ->setRecipientType('USER')
                            ->setRecipientEmail($operator->getEmail())
                            ->setChannel('EMAIL')
                            ->setSubject($rendered['subject'])
                            ->setBody($rendered['body'])
                            ->setPriority($rule->getPriority())
                            ->setScheduledAt(new \DateTime());
                        $this->queueRepository->save($q);
                    }
                }
            }
            $output->writeln("Financial rule {$rule->getRuleKey()}: processed " . count($invoices) . " invoices");
        }
    }
}
```

**Note on `EbitNote::getCode()` and `EbitNote::getShipment()`:** Read `src/Entity/EbitNote.php` before implementing to confirm these getter names are correct.

### Commit

```bash
git add src/Command/NotificationSchedulerCommand.php
git commit -m "feat: add deadline and financial notification scheduler command"
```

---

## Task 6: Register Services + Seed Default Rules Migration

**Repo:** `d:\Projects\make-cargo-client`

### Checklist

- [ ] Modify `config/services.yaml` — add `NotificationGeneratorService` and `NotificationTemplateRenderer` to `app.auto_service_locator`
- [ ] Create `migrations/mysql/Version20260624240000.php` — seed default rules + templates
- [ ] Create `migrations/sqlite/Version20260624240000.php` — seed default rules + templates
- [ ] Commit

### Modify `config/services.yaml`

Add to `app.auto_service_locator` arguments (after `InAppNotificationService` added in Task 2):

```yaml
                App\Service\NotificationGeneratorService: '@App\Service\NotificationGeneratorService'
                App\Service\NotificationTemplateRenderer: '@App\Service\NotificationTemplateRenderer'
```

### Migration: `migrations/mysql/Version20260624240000.php` — seed data

Seeds 6 default `notification_rule` rows and 5 `notification_template` rows. Uses `NOW()` for `created_date`.

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624240000 extends AbstractMigration
{
    public function getDescription(): string { return 'Seed default notification rules and templates'; }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO notification_rule (rule_key, name, trigger_type, trigger_config, recipient_config, channels, template_key, is_active, scope_type, priority, created_date) VALUES
('MILESTONE_VESSEL_DEPARTED','Vessel Departed','MILESTONE','{\"milestone_code\":\"VESSEL_DEPARTED\"}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_milestone_vessel_departed',1,'GLOBAL','NORMAL',NOW()),
('MILESTONE_VESSEL_ARRIVED','Vessel Arrived','MILESTONE','{\"milestone_code\":\"VESSEL_ARRIVED\"}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_milestone_vessel_arrived',1,'GLOBAL','NORMAL',NOW()),
('MILESTONE_DELIVERED','Cargo Delivered','MILESTONE','{\"milestone_code\":\"DELIVERED\"}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_milestone_delivered',1,'GLOBAL','HIGH',NOW()),
('STATUS_CHANGE','Job Status Changed','STATUS_CHANGE','{}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\"]',NULL,1,'GLOBAL','NORMAL',NOW()),
('CUTOFF_SI_48H','SI Cutoff in 48h','DEADLINE','{\"deadline_field\":\"booking.cutoff_si\",\"hours_before\":48}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_cutoff_si_48h',1,'GLOBAL','HIGH',NOW()),
('INVOICE_OVERDUE_7D','Invoice Overdue 7 Days','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":7}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','HIGH',NOW())
");

        $this->addSql("INSERT INTO notification_template (key_col, name, channel, subject_template, body_template, language, created_date) VALUES
('email_milestone_vessel_departed','Vessel Departed Email','EMAIL','Vessel Departed — {{ shipment_code }}','<p>Shipment <strong>{{ shipment_code }}</strong> milestone: Vessel Departed on {{ actual_date }}.</p>','en',NOW()),
('email_milestone_vessel_arrived','Vessel Arrived Email','EMAIL','Vessel Arrived — {{ shipment_code }}','<p>Shipment <strong>{{ shipment_code }}</strong>: Vessel has arrived. Date: {{ actual_date }}.</p>','en',NOW()),
('email_milestone_delivered','Cargo Delivered Email','EMAIL','Cargo Delivered — {{ shipment_code }}','<p>Shipment <strong>{{ shipment_code }}</strong> has been delivered on {{ actual_date }}.</p>','en',NOW()),
('email_cutoff_si_48h','SI Cutoff Alert Email','EMAIL','SI Cutoff in {{ hours_remaining }}h — {{ shipment_code }}','<p>The SI cutoff for shipment <strong>{{ shipment_code }}</strong> is at {{ cutoff_si }}. Please submit the Shipping Instruction immediately.</p>','en',NOW()),
('email_invoice_overdue','Invoice Overdue Email','EMAIL','Invoice Overdue {{ days_overdue }} days — {{ invoice_code }}','<p>Invoice <strong>{{ invoice_code }}</strong> for shipment {{ shipment_code }} is {{ days_overdue }} days overdue. Please follow up with the client.</p>','en',NOW())
");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM notification_template WHERE key_col IN ('email_milestone_vessel_departed','email_milestone_vessel_arrived','email_milestone_delivered','email_cutoff_si_48h','email_invoice_overdue')");
        $this->addSql("DELETE FROM notification_rule WHERE rule_key IN ('MILESTONE_VESSEL_DEPARTED','MILESTONE_VESSEL_ARRIVED','MILESTONE_DELIVERED','STATUS_CHANGE','CUTOFF_SI_48H','INVOICE_OVERDUE_7D')");
    }
}
```

### Migration: `migrations/sqlite/Version20260624240000.php` — seed data

SQLite does not support `NOW()` in INSERTs the same way — use `datetime('now')` instead:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624240000 extends AbstractMigration
{
    public function getDescription(): string { return 'Seed default notification rules and templates'; }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO notification_rule (rule_key, name, trigger_type, trigger_config, recipient_config, channels, template_key, is_active, scope_type, priority, created_date) VALUES
('MILESTONE_VESSEL_DEPARTED','Vessel Departed','MILESTONE','{\"milestone_code\":\"VESSEL_DEPARTED\"}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_milestone_vessel_departed',1,'GLOBAL','NORMAL',datetime('now')),
('MILESTONE_VESSEL_ARRIVED','Vessel Arrived','MILESTONE','{\"milestone_code\":\"VESSEL_ARRIVED\"}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_milestone_vessel_arrived',1,'GLOBAL','NORMAL',datetime('now')),
('MILESTONE_DELIVERED','Cargo Delivered','MILESTONE','{\"milestone_code\":\"DELIVERED\"}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_milestone_delivered',1,'GLOBAL','HIGH',datetime('now')),
('STATUS_CHANGE','Job Status Changed','STATUS_CHANGE','{}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\"]',NULL,1,'GLOBAL','NORMAL',datetime('now')),
('CUTOFF_SI_48H','SI Cutoff in 48h','DEADLINE','{\"deadline_field\":\"booking.cutoff_si\",\"hours_before\":48}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_cutoff_si_48h',1,'GLOBAL','HIGH',datetime('now')),
('INVOICE_OVERDUE_7D','Invoice Overdue 7 Days','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":7}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','HIGH',datetime('now'))
");

        $this->addSql("INSERT INTO notification_template (key_col, name, channel, subject_template, body_template, language, created_date) VALUES
('email_milestone_vessel_departed','Vessel Departed Email','EMAIL','Vessel Departed — {{ shipment_code }}','<p>Shipment <strong>{{ shipment_code }}</strong> milestone: Vessel Departed on {{ actual_date }}.</p>','en',datetime('now')),
('email_milestone_vessel_arrived','Vessel Arrived Email','EMAIL','Vessel Arrived — {{ shipment_code }}','<p>Shipment <strong>{{ shipment_code }}</strong>: Vessel has arrived. Date: {{ actual_date }}.</p>','en',datetime('now')),
('email_milestone_delivered','Cargo Delivered Email','EMAIL','Cargo Delivered — {{ shipment_code }}','<p>Shipment <strong>{{ shipment_code }}</strong> has been delivered on {{ actual_date }}.</p>','en',datetime('now')),
('email_cutoff_si_48h','SI Cutoff Alert Email','EMAIL','SI Cutoff in {{ hours_remaining }}h — {{ shipment_code }}','<p>The SI cutoff for shipment <strong>{{ shipment_code }}</strong> is at {{ cutoff_si }}. Please submit the Shipping Instruction immediately.</p>','en',datetime('now')),
('email_invoice_overdue','Invoice Overdue Email','EMAIL','Invoice Overdue {{ days_overdue }} days — {{ invoice_code }}','<p>Invoice <strong>{{ invoice_code }}</strong> for shipment {{ shipment_code }} is {{ days_overdue }} days overdue. Please follow up with the client.</p>','en',datetime('now'))
");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM notification_template WHERE key_col IN ('email_milestone_vessel_departed','email_milestone_vessel_arrived','email_milestone_delivered','email_cutoff_si_48h','email_invoice_overdue')");
        $this->addSql("DELETE FROM notification_rule WHERE rule_key IN ('MILESTONE_VESSEL_DEPARTED','MILESTONE_VESSEL_ARRIVED','MILESTONE_DELIVERED','STATUS_CHANGE','CUTOFF_SI_48H','INVOICE_OVERDUE_7D')");
    }
}
```

### Commit

```bash
git add config/services.yaml migrations/mysql/Version20260624240000.php migrations/sqlite/Version20260624240000.php
git commit -m "feat: register notification services and seed default rules and templates"
```

---

## Task 7: Client BO — Wire Notification Bell to Real API

**Repo:** `d:\Projects\make-cargo-client-bo`

### Checklist

- [ ] Create `src/services/NotificationService.js`
- [ ] Modify `src/layouts/components/UserProfile.vue` — fix service call + mark-read on open + wire ping count
- [ ] Modify `src/layouts/components/NavBarNotifications.vue` — replace mock data with real API
- [ ] Commit

### `src/services/NotificationService.js`

```js
const BASE = 'my-profile'

export default {
  getNotifications(page) {
    return $api(`${BASE}/get-notifications/${page}`)
  },
  markRead(ids = null) {
    return $api(`${BASE}/mark-notifications-read`, {
      method: 'POST',
      body: ids ? { ids } : {},
    })
  },
}
```

### Modify `src/layouts/components/UserProfile.vue`

**Read this file before editing.** The current file calls `UserService.getNotifications(...)` on lines 20 and 49. The fix is:

1. Add import of `NotificationService`:
   ```js
   import NotificationService from '@/services/NotificationService'
   ```

2. Replace both occurrences of `UserService.getNotifications(currentPage.value + 1)` with `NotificationService.getNotifications(currentPage.value + 1)`.

3. In `onOpenNotification()`, after loading the first page, call mark-read and reset counter:
   ```js
   async function onOpenNotification() {
     if (currentPage.value === 0) {
       const response = await NotificationService.getNotifications(currentPage.value + 1)
       currentPage.value = response.currentPage
       totalPages.value = response.totalPages
       notifications.value = notifications.value.concat(response.list)
       // Mark all as read and reset badge
       NotificationService.markRead().then(() => {
         appStore.newEntities.notification = 0
       })
     }
     UserService.viewPage('manage-notification').then(() => {
       appStore.newEntities.notification = 0
     })
   }
   ```

4. In `endIntersect()`, replace the `UserService.getNotifications` call with `NotificationService.getNotifications`.

5. Keep the `UserService` import for `viewPage()` call — do not remove it entirely.

**Wiring ping count:** The ping is currently commented out in `App.vue` (lines 32–38). To wire the notification count from ping, uncomment the ping block in `App.vue` and add notification handling:
```js
setTimeout(async () => {
  if (!authStore.accessToken) return
  const response = await MyProfileService.ping()
  if (response?.newEntities) appStore.newEntities = response.newEntities
  if (typeof response?.notification === 'number') {
    appStore.newEntities.notification = response.notification
  }
}, 1000)
```

Also import `MyProfileService` in `App.vue`:
```js
import MyProfileService from '@/services/MyProfileService'
```

### Modify `src/layouts/components/NavBarNotifications.vue`

**Read this file before editing.** The current file has hardcoded mock `notifications` ref. Replace the entire `<script setup>` block with real API calls:

```vue
<script setup>
import NotificationService from '@/services/NotificationService'

const notifications = ref([])
const loading = ref(false)

async function loadNotifications() {
  loading.value = true
  try {
    const result = await NotificationService.getNotifications(1)
    notifications.value = (result?.list ?? []).map(n => ({
      id:       n.id,
      title:    n.title,
      subtitle: n.body,
      time:     n.createdDate ? new Date(n.createdDate).toLocaleDateString() : '',
      isSeen:   n.isRead,
      icon:     'tabler-bell',
      color:    (n.priority === 'HIGH' || n.priority === 'URGENT') ? 'error' : 'primary',
    }))
  } finally {
    loading.value = false
  }
}

onMounted(loadNotifications)

const removeNotification = async (notificationId) => {
  await NotificationService.markRead([notificationId])
  notifications.value = notifications.value.filter(n => n.id !== notificationId)
}

const markRead = async (ids) => {
  await NotificationService.markRead(ids)
  notifications.value.forEach(n => { if (ids.includes(n.id)) n.isSeen = true })
}

const markUnRead = (ids) => {
  notifications.value.forEach(n => { if (ids.includes(n.id)) n.isSeen = false })
}

const handleNotificationClick = (notification) => {
  if (!notification.isSeen) markRead([notification.id])
}
</script>
```

Keep the `<template>` block unchanged — it passes `notifications` to the `<Notifications>` component.

### Commit

```bash
# in d:\Projects\make-cargo-client-bo
git add src/services/NotificationService.js src/layouts/components/UserProfile.vue src/layouts/components/NavBarNotifications.vue src/App.vue
git commit -m "feat: wire notification bell to real API"
```

---

## Task 8: Client BO — Notification Preference Settings Page

**Repo:** `d:\Projects\make-cargo-client-bo` and `d:\Projects\make-cargo-client`

### Checklist

- [ ] Create `src/services/NotificationPreferenceService.js` (client BO)
- [ ] Create `src/pages/notifications.vue` (client BO)
- [ ] Modify `src/layouts/components/UserProfile.vue` (client BO) — link Settings to `/notifications`
- [ ] Modify `src/Controller/Api/MyProfileController.php` (client API) — add preference endpoints
- [ ] Commit both repos

### `src/services/NotificationPreferenceService.js`

```js
const BASE = 'my-profile'

export default {
  getPreferences() {
    return $api(`${BASE}/notification-preferences`)
  },
  savePreferences(preferences) {
    return $api(`${BASE}/notification-preferences`, {
      method: 'POST',
      body: preferences,
    })
  },
}
```

### `src/pages/notifications.vue`

```vue
<script setup>
import { useGettext } from 'vue3-gettext'
import NotificationPreferenceService from '@/services/NotificationPreferenceService'

definePage({ meta: { action: 'GET', subject: 'User' } })

const { $gettext } = useGettext()
const preferences = ref([])
const loading = ref(true)
const saving = ref(false)
const saved = ref(false)

onMounted(async () => {
  preferences.value = await NotificationPreferenceService.getPreferences() ?? []
  loading.value = false
})

async function save() {
  saving.value = true
  const payload = preferences.value.flatMap(rule =>
    rule.channels.map(ch => ({
      ruleKey:    rule.ruleKey,
      channel:    ch.channel,
      isEnabled:  ch.isEnabled,
      digestMode: ch.digestMode,
    }))
  )
  await NotificationPreferenceService.savePreferences(payload)
  saving.value = false
  saved.value = true
  setTimeout(() => { saved.value = false }, 3000)
}
</script>

<template>
  <div>
    <h1 class="text-h5 mb-2">{{ $gettext('Notification Preferences') }}</h1>
    <p class="text-body-2 text-medium-emphasis mb-6">
      {{ $gettext('Choose which notifications you receive and how.') }}
    </p>

    <VAlert v-if="saved" type="success" class="mb-4" density="compact">
      {{ $gettext('Preferences saved.') }}
    </VAlert>

    <VCard :loading="loading">
      <VTable>
        <thead>
          <tr>
            <th>{{ $gettext('Notification') }}</th>
            <th>{{ $gettext('Priority') }}</th>
            <th>{{ $gettext('Channel') }}</th>
            <th>{{ $gettext('Enabled') }}</th>
            <th>{{ $gettext('Digest') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="rule in preferences" :key="rule.ruleKey">
            <tr v-for="ch in rule.channels" :key="ch.channel">
              <td>{{ rule.name }}</td>
              <td>
                <VChip size="x-small" :color="rule.priority === 'HIGH' ? 'error' : 'default'">
                  {{ rule.priority }}
                </VChip>
              </td>
              <td><VChip size="x-small">{{ ch.channel }}</VChip></td>
              <td><VSwitch v-model="ch.isEnabled" density="compact" hide-details /></td>
              <td>
                <VSwitch
                  v-model="ch.digestMode"
                  density="compact"
                  hide-details
                  :disabled="!ch.isEnabled"
                />
              </td>
            </tr>
          </template>
          <tr v-if="!preferences.length && !loading">
            <td colspan="5" class="text-center text-medium-emphasis py-6">
              {{ $gettext('No notification rules configured.') }}
            </td>
          </tr>
        </tbody>
      </VTable>
      <VCardActions class="pa-4">
        <VBtn color="primary" :loading="saving" @click="save">
          {{ $gettext('Save Preferences') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
</template>
```

### Modify `src/layouts/components/UserProfile.vue` — wire Settings link

**Read this file before editing.** Find the Settings `VListItem` (currently at line 146):

```vue
<!-- 👉 Settings -->
<VListItem link>
```

Change to:

```vue
<!-- 👉 Settings -->
<VListItem link :to="{name: 'notifications'}">
```

### Modify `src/Controller/Api/MyProfileController.php` — add preference endpoints

Add these two methods to `MyProfileController`. Required additional imports:

```php
use App\Entity\UserNotificationPreference;
use App\Repository\NotificationRuleRepository;
use App\Repository\UserNotificationPreferenceRepository;
```

Methods to add:

```php
#[Route('/notification-preferences', methods: ['GET'])]
public function getNotificationPreferences(
    #[CurrentUser] User $user,
    UserNotificationPreferenceRepository $prefRepository,
    NotificationRuleRepository $ruleRepository,
): JsonResponse {
    $prefs = $prefRepository->findForUser($user);
    $rules = $ruleRepository->findBy(['isActive' => true]);

    $prefMap = [];
    foreach ($prefs as $p) {
        $prefMap[$p->getRuleKey()][$p->getChannel()] = [
            'isEnabled'  => $p->isEnabled(),
            'digestMode' => $p->isDigestMode(),
        ];
    }

    return $this->json(array_map(fn($r) => [
        'ruleKey'  => $r->getRuleKey(),
        'name'     => $r->getName(),
        'priority' => $r->getPriority(),
        'channels' => array_map(fn($ch) => [
            'channel'    => $ch,
            'isEnabled'  => $prefMap[$r->getRuleKey()][$ch]['isEnabled'] ?? true,
            'digestMode' => $prefMap[$r->getRuleKey()][$ch]['digestMode'] ?? false,
        ], $r->getChannels()),
    ], $rules));
}

#[Route('/notification-preferences', methods: ['POST'])]
public function saveNotificationPreferences(
    Request $request,
    #[CurrentUser] User $user,
    UserNotificationPreferenceRepository $prefRepository,
): JsonResponse {
    $body = json_decode($request->getContent(), true) ?? [];
    foreach ($body as $item) {
        $ruleKey = $item['ruleKey'] ?? '';
        $channel = $item['channel'] ?? '';
        if (!$ruleKey || !$channel) continue;

        $pref = $prefRepository->findOneBy([
            'user'    => $user,
            'ruleKey' => $ruleKey,
            'channel' => $channel,
        ]) ?? new UserNotificationPreference();

        $pref->setUser($user)
             ->setRuleKey($ruleKey)
             ->setChannel($channel)
             ->setIsEnabled((bool) ($item['isEnabled'] ?? true))
             ->setDigestMode((bool) ($item['digestMode'] ?? false));

        $prefRepository->savePreference($pref);
    }
    return $this->json(null, Response::HTTP_NO_CONTENT);
}
```

### Commits

```bash
# Client BO
git add src/services/NotificationPreferenceService.js src/pages/notifications.vue src/layouts/components/UserProfile.vue
git commit -m "feat: add notification preference settings page"

# Client API
git add src/Controller/Api/MyProfileController.php
git commit -m "feat: add notification preference API endpoints to MyProfileController"
```

---

## Task 9: Documentation Guide

**Repo:** `d:\Projects\make-cargo-client`

### Checklist

- [ ] Create `docs/guides/notifications.md`
- [ ] Commit

### `docs/guides/notifications.md`

Create the file with the following content:

```markdown
# Notification & Alert System — Setup Guide

## 1. Overview

The notification system delivers alerts to users via two channels:

- **In-app bell** — persisted in `in_app_notification`, served via `/my-profile/get-notifications/{page}`
- **Email queue** — persisted in `notification_queue`, dispatched via `app:notifications:process-queue`

**3-tier architecture:**

1. **Event tier** — `NotificationEventListener` fires on Doctrine `postPersist` (new `ShipmentMilestone`) and `postUpdate` (`Shipment.status` change). It dispatches an async `NotificationTriggerMessage` via Symfony Messenger.
2. **Generator tier** — `NotificationTriggerMessageHandler` receives the message and calls `NotificationGeneratorService`, which evaluates active `NotificationRule` records and creates `InAppNotification` and/or `NotificationQueue` records.
3. **Delivery tier** — `NotificationQueueProcessorCommand` picks up `PENDING` queue entries and sends them via `MailService::sendRaw()`. `NotificationSchedulerCommand` runs on a cron and generates deadline/financial alerts.

## 2. Database Tables

| Table | Purpose | Primary key |
|---|---|---|
| `in_app_notification` | Per-user bell notifications | `id` (auto-increment) |
| `notification_rule` | Configurable trigger rules | `id` (auto-increment); `rule_key` unique |
| `notification_template` | Email/in-app body templates | `key_col` (string, PK) |
| `notification_queue` | Email delivery queue | `id` (auto-increment) |
| `user_notification_preference` | Per-user opt-in/out per rule+channel | composite: `user_id` + `rule_key` + `channel` |

### Key fields

**`in_app_notification`:** `user_id`, `shipment_id` (nullable), `rule_key`, `title`, `body`, `priority` (NORMAL/HIGH/URGENT), `is_read`, `read_at`, `action_url`, `created_date`

**`notification_rule`:** `rule_key` (unique slug), `trigger_type`, `trigger_config` (JSON), `recipient_config` (JSON array), `channels` (JSON array), `template_key`, `is_active`, `scope_type`, `priority`

**`notification_queue`:** `status` (PENDING/SENT/FAILED/CANCELLED/SKIPPED), `channel`, `recipient_email`, `scheduled_at`, `attempt_count`, `last_error`

## 3. Notification Rule Configuration

### `triggerType` values

| Value | When it fires |
|---|---|
| `MILESTONE` | After a new `ShipmentMilestone` is persisted |
| `STATUS_CHANGE` | After `Shipment.status` changes |
| `DEADLINE` | Run by `app:notifications:schedule-deadlines` cron |
| `FINANCIAL` | Run by `app:notifications:schedule-deadlines` cron |

### `triggerConfig` format

```json
// MILESTONE — filter by milestone code
{"milestone_code": "VESSEL_DEPARTED"}

// STATUS_CHANGE — filter by new status (omit to match all)
{"new_status": "DELIVERED"}

// DEADLINE — SI cutoff window
{"deadline_field": "booking.cutoff_si", "hours_before": 48}

// FINANCIAL — invoice overdue
{"event": "INVOICE_OVERDUE", "days_overdue": 7}
```

### `recipientConfig` format

```json
[
  {"type": "JOB_OPERATOR"},
  {"type": "FIXED_EMAIL", "email": "ops@example.com"}
]
```

`JOB_OPERATOR` resolves to `Shipment::getCreatedBy()`.

### `channels` format

```json
["IN_APP", "EMAIL"]
```

## 4. Adding a New Notification Rule

```sql
-- 1. Add the rule
INSERT INTO notification_rule
  (rule_key, name, trigger_type, trigger_config, recipient_config, channels, template_key, is_active, scope_type, priority, created_date)
VALUES
  ('MILESTONE_CUSTOMS_RELEASED', 'Customs Released', 'MILESTONE',
   '{"milestone_code":"CUSTOMS_RELEASED"}',
   '[{"type":"JOB_OPERATOR"}]',
   '["IN_APP","EMAIL"]',
   'email_milestone_customs_released', 1, 'GLOBAL', 'NORMAL', NOW());

-- 2. Add the email template
INSERT INTO notification_template
  (key_col, name, channel, subject_template, body_template, language, created_date)
VALUES
  ('email_milestone_customs_released', 'Customs Released Email', 'EMAIL',
   'Customs Cleared — {{ shipment_code }}',
   '<p>Shipment <strong>{{ shipment_code }}</strong> has been customs cleared on {{ actual_date }}.</p>',
   'en', NOW());
```

To disable a rule without deleting it: `UPDATE notification_rule SET is_active = 0 WHERE rule_key = 'RULE_KEY';`

## 5. Template Variable Syntax

Templates use `{{ variable }}` placeholders (spaces optional: `{{variable}}` also works).

### Available variables by trigger type

**MILESTONE:**
- `{{ shipment_code }}` — shipment job code
- `{{ milestone_code }}` — raw enum value (e.g. `VESSEL_DEPARTED`)
- `{{ milestone_label }}` — customer-facing label (e.g. `Vessel departed`)
- `{{ actual_date }}` — `Y-m-d` formatted actual date

**STATUS_CHANGE:**
- `{{ shipment_code }}`
- `{{ old_status }}` — previous status value
- `{{ new_status }}` — new status value

**DEADLINE (booking.cutoff_si):**
- `{{ shipment_code }}`
- `{{ hours_remaining }}` — hours until cutoff (from `hours_before` config)
- `{{ cutoff_si }}` — formatted cutoff datetime (`Y-m-d H:i`)

**FINANCIAL (INVOICE_OVERDUE):**
- `{{ shipment_code }}`
- `{{ invoice_code }}` — EbitNote code
- `{{ days_overdue }}` — days overdue from `days_overdue` config

## 6. How Event Triggering Works

```
ShipmentMilestone persisted
    ↓
NotificationEventListener::postPersist()
    ↓
MessageBus::dispatch(NotificationTriggerMessage('milestone.created', $id))
    ↓ [async transport — Messenger worker]
NotificationTriggerMessageHandler::__invoke()
    ↓
NotificationGeneratorService::handleMilestone()
    ↓
Finds matching active NotificationRule records
    ↓
For each rule → InAppNotification + NotificationQueue records created
```

The same flow applies for `Shipment.status` changes via `postUpdate`.

Start the Messenger worker with:
```bash
php bin/console messenger:consume async --time-limit=3600
```

## 7. Email Delivery

Email entries in `notification_queue` with `status = PENDING` and `scheduled_at <= now` are processed by:

```bash
php bin/console app:notifications:process-queue
```

The command:
- Fetches up to 50 due records
- Calls `MailService::sendRaw($email, $subject, $htmlBody)` via the Brevo/SMTP transport
- Marks records `SENT` (success) or retries up to 3 times before marking `FAILED`

### Cron schedule (see section 11)

`*/5 * * * *` — process queue every 5 minutes.

## 8. Deadline Scheduler

The scheduler command generates notifications for time-based triggers:

```bash
php bin/console app:notifications:schedule-deadlines
```

### Supported `deadline_field` values

| Value | Entity field | Description |
|---|---|---|
| `booking.cutoff_si` | `Booking::getSiCutOff()` | SI (Shipping Instruction) submission cutoff |

To add support for additional deadline fields (e.g. `booking.cutoff_cy`), add a new `if ($field !== '...')` branch in `NotificationSchedulerCommand::processDeadlineRules()` with the appropriate query builder logic.

### Financial events

| Event | Query | Description |
|---|---|---|
| `INVOICE_OVERDUE` | `EbitNote` where `type = InvoiceDebit`, `status NOT IN ('P','D')`, `createdDate <= now - days_overdue` | Unpaid AR invoice past due |

## 9. In-App Notification Bell

### API endpoints

| Method | URL | Description |
|---|---|---|
| `GET` | `/my-profile/get-notifications/{page}` | Paginated list (20/page), ordered newest first |
| `POST` | `/my-profile/mark-notifications-read` | Mark notifications read. Body: `{"ids": [1,2,3]}` or `{}` to mark all |
| `GET` | `/my-profile/ping` | Includes `notification: <unread_count>` in response |

### Response format for `get-notifications`

```json
{
  "currentPage": 1,
  "totalPages": 3,
  "total": 42,
  "list": [
    {
      "id": 123,
      "title": "Vessel Departed",
      "body": "Shipment JOB-001: Vessel departed",
      "priority": "NORMAL",
      "isRead": false,
      "actionUrl": null,
      "shipmentId": 45,
      "shipment": {"code": "JOB-001"},
      "ruleKey": "MILESTONE_VESSEL_DEPARTED",
      "createdDate": "2026-06-24T09:00:00+00:00"
    }
  ]
}
```

### How the BO bell works

1. `ping()` is called on login / periodically. The response includes `notification: N` (unread count).
2. `appStore.newEntities.notification` holds the badge number — the bell badge is bound to this.
3. When the bell is opened (`onOpenNotification`), `NotificationService.getNotifications(1)` is called. After load, `NotificationService.markRead()` is called to clear all, resetting the badge to 0.
4. Infinite scroll in `UserProfile.vue` calls subsequent pages via `endIntersect()`.
5. `NavBarNotifications.vue` uses a separate `Notifications` component and loads via `NotificationService.getNotifications(1)` on mount.

## 10. User Preference API

Users can opt-in/out of specific rule+channel combinations.

### Endpoints

| Method | URL | Description |
|---|---|---|
| `GET` | `/my-profile/notification-preferences` | Returns all active rules with user's current preferences |
| `POST` | `/my-profile/notification-preferences` | Upsert preferences array |

### GET response format

```json
[
  {
    "ruleKey": "MILESTONE_VESSEL_DEPARTED",
    "name": "Vessel Departed",
    "priority": "NORMAL",
    "channels": [
      {"channel": "IN_APP", "isEnabled": true, "digestMode": false},
      {"channel": "EMAIL", "isEnabled": true, "digestMode": false}
    ]
  }
]
```

### POST payload format

```json
[
  {"ruleKey": "MILESTONE_VESSEL_DEPARTED", "channel": "EMAIL", "isEnabled": false, "digestMode": false},
  {"ruleKey": "CUTOFF_SI_48H", "channel": "IN_APP", "isEnabled": true, "digestMode": false}
]
```

**Note:** `UserNotificationPreference` records are NOT currently checked by `NotificationGeneratorService` — preferences are stored for display only in this MVP. To enforce them, add a lookup in `NotificationGeneratorService::dispatchToUser()` before creating `InAppNotification`/`NotificationQueue` entries.

## 11. Cron Schedule

Add these to the server crontab (or Supervisor):

```cron
# Process email queue every 5 minutes
*/5 * * * * /usr/bin/php /var/www/app/bin/console app:notifications:process-queue >> /var/log/notifications-queue.log 2>&1

# Generate deadline and financial alerts every hour
0 * * * * /usr/bin/php /var/www/app/bin/console app:notifications:schedule-deadlines >> /var/log/notifications-scheduler.log 2>&1

# Symfony Messenger worker (run as a persistent process via Supervisor)
# php bin/console messenger:consume async --time-limit=3600
```

## 12. Channels Not Yet Implemented

The following channels are reserved for future implementation:

- **SMS** — requires Twilio or similar; not configured
- **WhatsApp** — requires WhatsApp Business API
- **Webhook** — push to external URL on event
- **Digest mode** — batch multiple notifications into a single daily/weekly email

To add a new channel, extend `NotificationGeneratorService::dispatchToUser()` and `NotificationQueueProcessorCommand::execute()` with a new `if ($channel === 'YOUR_CHANNEL')` branch.
```

### Commit

```bash
git add docs/guides/notifications.md
git commit -m "docs: add notification system guide"
```

---

## Self-Review

### Spec coverage check

| Trigger type | Rule | Status |
|---|---|---|
| Milestone: VESSEL_DEPARTED | `MILESTONE_VESSEL_DEPARTED` | Seeded in Task 6 migration |
| Milestone: VESSEL_ARRIVED | `MILESTONE_VESSEL_ARRIVED` | Seeded in Task 6 migration |
| Milestone: DELIVERED | `MILESTONE_DELIVERED` | Seeded in Task 6 migration |
| Status change (any) | `STATUS_CHANGE` | Seeded in Task 6 migration |
| Deadline: SI cutoff 48h | `CUTOFF_SI_48H` | Seeded in Task 6 migration |
| Financial: Invoice overdue 7d | `INVOICE_OVERDUE_7D` | Seeded in Task 6 migration |

All 6 default rule types from the spec are covered.

### No placeholder steps

All 9 tasks contain complete, executable code. No `// TODO` stubs or partial implementations. Known unknowns are documented as pre-read instructions (e.g. "Read `Booking.php` before implementing").

### Type/method consistency across tasks

| Method | Used in | Consistent |
|---|---|---|
| `InAppNotificationService::create()` | Tasks 2, 3, 5 | Yes — same 7-param signature throughout |
| `InAppNotificationRepository::save($entity, $request=null)` | Task 1 | Matches `BaseRepository::save()` signature |
| `MailService::sendRaw($to, $subject, $html)` | Tasks 2, 4 | Defined in Task 2, used in Task 4 |
| `NotificationTemplateRenderer::render($key, $vars)` | Tasks 3, 5 | Consistent `['subject', 'body']` return |
| `NotificationQueueRepository::findPendingDue($limit)` | Tasks 1, 4 | Defined in Task 1, used in Task 4 |
| `NotificationRuleRepository::findActiveDeadlineRules()` | Tasks 1, 5 | Defined in Task 1, used in Task 5 |
| `NotificationRuleRepository::findActiveFinancialRules()` | Tasks 1, 5 | Defined in Task 1, used in Task 5 |
| `Booking::getSiCutOff()` | Task 5 | Verified against `Booking.php` — actual getter name |
| `ShipmentMilestone::getMilestoneCode()` | Tasks 1, 3 | Returns `?MilestoneCode`; null-checked before `.value` |
| `Shipment::getStatus()` | Task 3 | Returns `?ShipmentStatus` enum; change set values extracted with `?->value` |

### Codebase pattern compliance

- All repositories extend `BaseRepository` with the exact `save($entity, ?Request $request = null)` signature.
- `InAppNotificationService` does NOT extend `BaseService` (standalone, used in background jobs).
- `NotificationGeneratorService` does NOT extend `BaseService` (standalone).
- `NotificationTemplateRenderer` does NOT extend `BaseService` (standalone).
- `NotificationEventListener` uses `#[AsDoctrineListener]` attribute (Symfony 7 style).
- All new services that need DI access via the locator are added to `app.auto_service_locator` in Task 6.
- Migration namespaces: `DoctrineMigrations` for mysql, `SqlEngineMigrations` for sqlite.
- Migration versions continue from `20260624180000` → `190000` through `240000`.
- `NotificationTemplate` uses `key_col` as the actual column name to avoid MySQL reserved word `key`.
- `UserNotificationPreference` uses composite primary key with no `EntityDateTimeAbleTrait` (no lifecycle callbacks needed).
- `UserProfile.vue` keeps the `UserService` import for `viewPage()` — only the `getNotifications` call is replaced.
- `vue3-gettext` is imported explicitly in `notifications.vue` as per BO pattern.
- `$api` is a global (not imported) in BO service files.
- `ref`, `computed`, `onMounted` are auto-imported in BO Vue files (not explicitly imported).
