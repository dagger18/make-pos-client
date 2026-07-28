<?php
namespace App\Command;

use App\Module\Notification\Repository\NotificationQueueRepository;
use App\Module\Core\Service\MailService;
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
