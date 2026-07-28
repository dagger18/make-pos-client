<?php
namespace App\Command;

use App\Module\Notification\Entity\NotificationQueue;
use App\Module\Finance\Enum\EbitNoteStatus;
use App\Module\Finance\Enum\EbitNoteType;
use App\Module\Finance\Repository\EbitNoteRepository;
use App\Module\Notification\Repository\NotificationQueueRepository;
use App\Module\Notification\Repository\NotificationRuleRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Notification\Service\InAppNotificationService;
use App\Module\Notification\Service\NotificationTemplateRenderer;
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
                ->setParameter('paidStatuses', [EbitNoteStatus::Pending, EbitNoteStatus::Done])
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
