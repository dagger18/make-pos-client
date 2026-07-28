<?php
namespace App\Module\Notification\Service;

use App\Module\Notification\Entity\NotificationQueue;
use App\Module\Operations\Entity\Shipment;
use App\Module\Operations\Entity\ShipmentMilestone;
use App\Module\Core\Entity\User;
use App\Module\Notification\Repository\NotificationQueueRepository;
use App\Module\Notification\Repository\NotificationRuleRepository;
use App\Module\Core\Repository\UserRepository;

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
