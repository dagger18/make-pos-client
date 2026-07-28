<?php

namespace App\Misc\Command;

use App\Module\Core\Entity\Media;
use App\Module\Core\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:media:cleanup', description: 'Remove orphan media (no parent entity linked)')]
class MediaCleanupCommand extends Command
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List orphans without deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $cutoff = new \DateTimeImmutable('-2 days');
        $orphans = $this->mediaRepository->createQueryBuilder('m')
            ->where('m.parentType IS NULL')
            ->andWhere('m.parentId IS NULL')
            ->andWhere('m.parentProperty IS NULL')
            ->andWhere('m.createdDate < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();

        if (empty($orphans)) {
            $io->success('No orphan media found.');
            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Name', 'Path', 'Created'],
            array_map(static fn(Media $m) => [
                $m->getId(),
                $m->getName(),
                $m->getPath(),
                $m->getCreatedDate()?->format('Y-m-d H:i'),
            ], $orphans)
        );

        if ($dryRun) {
            $io->note(sprintf('Found %d orphan(s). Run without --dry-run to delete.', count($orphans)));
            return Command::SUCCESS;
        }

        foreach ($orphans as $media) {
            $this->em->remove($media);
        }
        $this->em->flush();

        $io->success(sprintf('Deleted %d orphan media file(s).', count($orphans)));
        return Command::SUCCESS;
    }
}
