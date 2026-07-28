<?php
namespace App\Command;

use App\Module\Core\Repository\MediaRepository;
use App\Module\Core\Service\MediaService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:media:sync',
    description: 'Clone media files that exist on one storage hub to the other'
)]
class MediaSyncCommand extends Command
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly MediaService    $mediaService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $toBackup = $this->mediaRepository->findNeedingBackupSync(50);
        $output->writeln(sprintf('Syncing %d file(s) to backup hub', count($toBackup)));
        foreach ($toBackup as $media) {
            try {
                $this->mediaService->syncMediaToBackup($media);
                $output->writeln('  ✓ ' . $media->getPath());
            } catch (\Throwable $e) {
                $this->logger->warning('media:sync backup failed', ['path' => $media->getPath(), 'error' => $e->getMessage()]);
                $output->writeln('  ✗ ' . $media->getPath() . ' — ' . $e->getMessage());
            }
        }

        $toS3 = $this->mediaRepository->findNeedingS3Sync(50);
        $output->writeln(sprintf('Syncing %d file(s) to S3', count($toS3)));
        foreach ($toS3 as $media) {
            try {
                $this->mediaService->syncMediaToS3($media);
                $output->writeln('  ✓ ' . $media->getPath());
            } catch (\Throwable $e) {
                $this->logger->warning('media:sync s3 failed', ['path' => $media->getPath(), 'error' => $e->getMessage()]);
                $output->writeln('  ✗ ' . $media->getPath() . ' — ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
