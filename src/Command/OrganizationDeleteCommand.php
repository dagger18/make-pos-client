<?php

namespace App\Command;

use App\Module\Core\Repository\MediaRepository;
use App\Module\Core\Service\ConfigService;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'organization:delete',
    description: 'Delete org S3 and backup storage before infrastructure teardown'
)]
class OrganizationDeleteCommand extends Command
{
    public function __construct(
        private ConfigService $configService,
        private MediaRepository $mediaRepository,
        private FilesystemOperator $mediaStorage,
        private HttpClientInterface $client,
        private ParameterBagInterface $params,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('token', InputArgument::REQUIRED, 'Organization token');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $token = $input->getArgument('token');

        if (empty($token) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $token)) {
            $output->writeln("<error>Invalid token: '{$token}'</error>");
            return self::FAILURE;
        }

        if (!$this->configService->isConfigExists('organization')) {
            $output->writeln("<comment>Organization config not found for {$token} — skipping.</comment>");
            return self::SUCCESS;
        }

        // Delete all S3 files under the org token prefix
        try {
            $this->mediaStorage->deleteDirectory($token);
            $output->writeln("<info>S3 directory '{$token}' deleted.</info>");
        } catch (\Throwable $e) {
            $output->writeln("<error>S3 deletion failed: {$e->getMessage()}</error>");
            //return self::FAILURE;
        }

        // Delete all backup-server files for each Media record
        $backupBase = rtrim($this->params->get('backup_media_url'), '/');
        $apiKey = $this->params->get('backup_media_api_key');
        $allMedia = $this->mediaRepository->findAll();
        $backupErrors = 0;

        foreach ($allMedia as $media) {
            try {
                $response = $this->client->request('DELETE', $backupBase . '/file/' . $media->getPath(), [
                    'headers' => ['X-Api-Key' => $apiKey],
                    'timeout' => 15,
                ]);
                $status = $response->getStatusCode();
                if ($status >= 300 && $status !== 404) {
                    $output->writeln("<comment>Backup deletion failed for {$media->getPath()}: HTTP {$status}</comment>");
                    $backupErrors++;
                }
            } catch (\Throwable $e) {
                $output->writeln("<comment>Backup deletion error for {$media->getPath()}: {$e->getMessage()}</comment>");
                $backupErrors++;
            }
        }

        $output->writeln("<info>Backup cleanup done ({$backupErrors} skipped).</info>");
        return self::SUCCESS;
    }
}
