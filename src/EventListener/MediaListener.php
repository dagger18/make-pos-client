<?php

namespace App\EventListener;

use App\Module\Core\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsEntityListener(event: Events::postRemove, entity: Media::class)]
class MediaListener
{
    public function __construct(
        private FilesystemOperator $mediaStorage,
        private HttpClientInterface $httpClient,
        private ParameterBagInterface $params,
        private LoggerInterface $logger
    ) {}

    public function postRemove(Media $entity): void
    {
        $path = $entity->getPath();
        if (!$path) {
            return;
        }

        $this->deleteFromStorage($path);
        $this->deleteFromBackup($path);

        if ($entity->isHasWebp()) {
            $webpPath = pathinfo($path, PATHINFO_DIRNAME) . '/' . pathinfo($path, PATHINFO_FILENAME) . '.webp';
            $this->deleteFromStorage($webpPath);
            $this->deleteFromBackup($webpPath);
        }

        if ($entity->isHasThumb()) {
            $thumbPath = pathinfo($path, PATHINFO_DIRNAME) . '/thumb_' . pathinfo($path, PATHINFO_FILENAME) . '.' . pathinfo($path, PATHINFO_EXTENSION);
            $this->deleteFromStorage($thumbPath);
            $this->deleteFromBackup($thumbPath);
        }
    }

    private function deleteFromStorage(string $path): void
    {
        try {
            $this->mediaStorage->delete($path);
        } catch (FilesystemException $e) {
            $this->logger->warning('Failed to delete media from storage', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }

    private function deleteFromBackup(string $path): void
    {
        $backupUrl = rtrim($this->params->get('backup_media_url'), '/') . '/file/' . $path;
        try {
            $this->httpClient->request('DELETE', $backupUrl, [
                'headers' => ['X-Api-Key' => $this->params->get('backup_media_api_key')],
                'timeout' => 5,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to delete media from backup server', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }
}
