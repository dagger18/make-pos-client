<?php
namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Entity\Media;
use App\Module\Core\Enum\EntityType;
use App\Module\Core\Repository\MediaRepository;
use Aws\S3\S3Client;
use Dompdf\Dompdf;
use Dompdf\Options;
use FFMpeg;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Liip\ImagineBundle\Model\Binary;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Cache\ItemInterface;
use App\Misc\Enum\CapacityType;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;
/**
 * Class MediaService
 * @package App\Service
 */
class MediaService extends BaseService
{
    /**
     * MediaService constructor.
     * @param MediaRepository $repository
     */
    public function __construct(
        protected BaseService $baseService,
        public MediaRepository $repository,
        private readonly SerializerInterface&DenormalizerInterface $denormalizer,
        protected Environment $twig,
        protected LocaleSwitcher $localeSwitcher,
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigService $configService,
        private readonly FilterManager $filterManager,
        private readonly CapacityService $capacityService,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function saveString($fileName, $content, $data)
    {
        $detector = new FinfoMimeTypeDetector();
        $data['name'] = $fileName;
        $data['type'] = pathinfo($fileName, PATHINFO_EXTENSION);
        $data['mime'] = $detector->detectMimeType($fileName, $content);
        $hash = bin2hex(random_bytes(16));
        $data['path'] = sprintf('%s/%s/%s.%s', substr($hash, 0, 2), substr($hash, 2, 2), substr($hash, 4), $data['type']) ;
        $data['size'] = strlen($content);
        $this->mediaStorage->write($data['path'], $content);
        $mediaEntity = $this->denormalizer->denormalize($data, Media::class);
        $mediaEntity->setCreatedBy($this->getUser());
        $this->repository->save($mediaEntity);
        return $mediaEntity;
    }

    public function parseDuration(UploadedFile $file) {
        $ffprobe    = FFMpeg\FFProbe::create([
            'ffmpeg.binaries'  => $this->params->get('ffmpeg.binaries'),
            'ffprobe.binaries' => $this->params->get('ffprobe.binaries'),
            'timeout'          => 3600, // The timeout for the underlying process
            'ffmpeg.threads'   => 12,   // The number of threads that FFMpeg should use
        ]);
        return (int) $ffprobe->format($file->getPathName())->get('duration');
    }

    public function parseVideoDimension(UploadedFile $file) {
        $ffprobe = FFMpeg\FFProbe::create([
            'ffmpeg.binaries'  => $this->params->get('ffmpeg.binaries'),
            'ffprobe.binaries' => $this->params->get('ffprobe.binaries'),
            'timeout'          => 3600, // The timeout for the underlying process
            'ffmpeg.threads'   => 12,   // The number of threads that FFMpeg should use
        ]);
        $video_dimensions = $ffprobe
            ->streams($file->getPathName())   // extracts streams informations
            ->videos()                      // filters video streams
            ->first()                       // returns the first video stream
            ->getDimensions();              // returns a FFMpeg\Coordinate\Dimension object
        $width = $video_dimensions->getWidth();
        $height = $video_dimensions->getHeight();
        return [$width, $height];
    }

    private function processFileUpload(UploadedFile $file) {
        $hash = bin2hex(random_bytes(4));
        $sanitizedFilename = $this->sanitizeFilename($file->getClientOriginalName());
        $filePath = sprintf('%s/%s/%s', substr($hash, 0, 2), substr($hash, 2, 2), $sanitizedFilename);
        $stream = fopen($file->getRealPath(), 'r+');
        $this->mediaStorage->writeStream($filePath, $stream);
        if($this->getUser()){
            $media['createdBy'] = $this->getUser()->getId();
        }
        $media['name'] = $sanitizedFilename;
        $media['type'] = $file->guessExtension();
        $media['mime'] = $file->getMimeType();
        $media['path'] = $filePath;
        $media['size'] = $file->getSize();
        if(str_starts_with($file->getMimeType(), 'audio/') || str_starts_with($file->getMimeType(), 'video/')) {
            $media['duration'] = $this->parseDuration($file);
        }
        if(str_starts_with($file->getMimeType(), 'image/')) {
            list($media['width'], $media['height']) = getimagesize($file->getPathName());
        }
        if(str_starts_with($file->getMimeType(), 'video/')) {
            list($media['width'], $media['height']) = $this->parseVideoDimension($file);
        }
        return $media;
    }

    public function handleFileUpload(&$data, $files, $entityClass) {
        forEach($files->getIterator() as $field => $file) {
            $this->logger->info('check file data', [$field, $file]);
            if($file instanceof UploadedFile) {
                $media = $this->processFileUpload($file);
                if($entityClass === Media::class) {
                    $data += $media;
                    return;
                } 

                $mediaEntity = $this->denormalizer->denormalize($media, Media::class, null, ['groups' => []]);
                if($this->getUser()){
                    $mediaEntity->setCreatedBy($this->getUser());
                }
                $this->repository->save($mediaEntity);
                $data[$field] = $mediaEntity->getId();
            } else if (is_array($file)) {
                forEach($file as $index => $subFile) {
                    $media = $this->processFileUpload($subFile);
                    $mediaEntity = $this->denormalizer->denormalize($media, Media::class, null, ['groups' => []]);
                    if($this->getUser()){
                        $mediaEntity->setCreatedBy($this->getUser());
                    }                   
                    $this->repository->save($mediaEntity);
                    $data[$field][$index] = $mediaEntity->getId();
                }
            }
        }
        
    }

    public function generatePdf($entity, $data, $language, $stream = true) {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'DejaVu Sans');
        $pdfOptions->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($pdfOptions);
        $this->localeSwitcher->setLocale($language);
        $html = $this->twig->render($data['template'], $data);

        $this->localeSwitcher->reset();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        date_default_timezone_set('UTC');
        /*
        $entityName = $this->commonService->getClassName($entity);
        $media = $this->saveString($data['filename'], $dompdf->output(), [
            'parentId' => $entity->getId(),
            'parentType' => constant("App\Misc\Enum\EntityType::" . ucfirst($entityName))->value,
            'parentProperty' => 'pdf',
            'language' => $language
        ]);
        $entity->addPdf($media);
        $this->{$entityName . 'Service'}->repository->save($entity);
        if(!$stream) return $media;
        */
        $dompdf->stream($data['filename'], [
            "Attachment" => true
        ]);
        return $dompdf;
    }

    public function replicate(Media $fromMedia, int $parentId) {
        $hash = bin2hex(random_bytes(16));
        $extension = $fromMedia->getType();
        $filePath = sprintf('%s/%s/%s.%s', substr($hash, 0, 2), substr($hash, 2, 2), substr($hash, 4), $extension) ;
        $this->mediaStorage->copy($fromMedia->getPath(), $filePath);
        $media = clone $fromMedia;
        $media->setParentId($parentId);
        $media->setPath($filePath);
        $media->setCreatedBy($this->getUser());
        $media->setCreatedDate(new \DateTime('now'));
        $this->repository->save($media);
        return $media;
    }

    public function getUploadUrls(string $filename, string $mimeType, int $size): array
    {
        $path = $this->generateStoragePath($filename);

        $s3Client = $this->getS3Client();
        $cmd = $s3Client->getCommand('PutObject', [
            'Bucket' => $this->params->get('aws.s3.bucket'),
            'Key' => $path,
            'ContentType' => $mimeType,
        ]);
        $s3UploadUrl = (string) $s3Client->createPresignedRequest($cmd, '+30 minutes')->getUri();

        $backupBase = rtrim($this->params->get('backup_media_url'), '/');
        $backupResponse = $this->httpClient->request('POST', $backupBase . '/presign', [
            'headers' => ['X-Api-Key' => $this->params->get('backup_media_api_key')],
            'json' => ['path' => $path],
        ]);
        $backupUploadUrl = $backupBase . $backupResponse->toArray()['uploadUrl'];

        $token = bin2hex(random_bytes(16));
        $this->appCache->get('media_presign_' . $token, function (ItemInterface $item) use ($path, $filename, $mimeType, $size) {
            $item->expiresAfter(1800);
            return ['path' => $path, 'name' => $filename, 'mime' => $mimeType, 'size' => $size];
        });

        return [
            'confirmToken' => $token,
            'path' => $path,
            's3UploadUrl' => $s3UploadUrl,
            'backupUploadUrl' => $backupUploadUrl,
        ];
    }

    public function confirmUpload(string $confirmToken, array $parentData, Request $request): Media
    {
        $notFound = false;
        $data = $this->appCache->get('media_presign_' . $confirmToken, function (ItemInterface $item) use (&$notFound) {
            $notFound = true;
            $item->expiresAfter(0);
            return null;
        });
        if ($notFound || $data === null) {
            throw new \InvalidArgumentException('Invalid or expired upload token');
        }
        $this->appCache->delete('media_presign_' . $confirmToken);

        $media = new Media();
        $media->setPath($data['path']);
        $media->setName($data['name']);
        $media->setMime($data['mime']);
        $media->setSize($data['size']);
        $media->setType(pathinfo($data['name'], PATHINFO_EXTENSION));
        if ($this->getUser()) {
            $media->setCreatedBy($this->getUser());
        }
        if (!empty($parentData['parentType'])) {
            $media->setParentType(EntityType::from($parentData['parentType']));
            $media->setParentId((int) ($parentData['parentId'] ?? null) ?: null);
            $media->setParentProperty($parentData['parentProperty'] ?? null);
        }

        if (isset($parentData['s3Success'])) {
            $media->setS3Success((bool) $parentData['s3Success']);
        }
        if (isset($parentData['backupSuccess'])) {
            $media->setBackupSuccess((bool) $parentData['backupSuccess']);
        }

        $sizeMb = (int) ceil($data['size'] / 1_048_576);
        $this->capacityService->assertAllowed(CapacityType::NetworkBandwidth, $sizeMb);
        $this->repository->save($media, $request);
        $this->configService->incrementBandwidth($data['size']);
        $this->capacityService->recordUsage(CapacityType::NetworkBandwidth, $sizeMb);

        return $media;
    }

    public function generateThumbnail(Media $media): void
    {

        try {
            $content = $this->mediaStorage->read($media->getPath());
            $binary = new Binary($content, $media->getMime());
            $filtered = $this->filterManager->applyFilter($binary, 'avatar_thumb');
            $thumbPath = $this->getThumbPath($media->getPath());

            $this->mediaStorage->write($thumbPath, $filtered->getContent());

            // Also push to backup server (non-blocking)
            try {
                $backupBase = rtrim($this->params->get('backup_media_url'), '/');
                $presignResp = $this->httpClient->request('POST', $backupBase . '/presign', [
                    'headers' => ['X-Api-Key' => $this->params->get('backup_media_api_key')],
                    'json' => ['path' => $thumbPath],
                ]);
                $backupUploadUrl = $backupBase . $presignResp->toArray()['uploadUrl'];
                $this->httpClient->request('PUT', $backupUploadUrl, [
                    'body' => $filtered->getContent(),
                    'headers' => ['Content-Type' => $filtered->getMimeType()],
                ]);
            } catch (\Throwable) {}

            $media->setHasThumb(true);
            $this->repository->save($media);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to generate thumbnail', [
                'path' => $media->getPath(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getThumbPath(string $path): string
    {
        $dir = pathinfo($path, PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return $dir . '/thumb_' . $filename . '.' . $ext;
    }

    public function getS3DownloadUrl(string $path): string
    {
        $cmd = $this->getS3Client()->getCommand('GetObject', [
            'Bucket' => $this->params->get('aws.s3.bucket'),
            'Key' => $path,
        ]);
        return (string) $this->getS3Client()->createPresignedRequest($cmd, '+10 seconds')->getUri();
    }

    public function getBackupFileUrl(string $path): string
    {
        return rtrim($this->params->get('backup_media_url'), '/') . '/file/' . $path;
    }

    public function generateDownloadToken(Media $media): string
    {
        $token = bin2hex(random_bytes(20));
        $this->appCache->get('media_dl_' . $token, function (ItemInterface $item) use ($media) {
            $item->expiresAfter(3600);
            return ['id' => $media->getId(), 'path' => $media->getPath()];
        });
        return $token;
    }

    public function generateThumbnailDownloadToken(Media $media): string
    {
        $token = bin2hex(random_bytes(20));
        $this->appCache->get('media_dl_' . $token, function (ItemInterface $item) use ($media) {
            $item->expiresAfter(3600);
            return ['id' => $media->getId(), 'path' => $this->getThumbPath($media->getPath())];
        });
        return $token;
    }

    public function consumeDownloadToken(string $token): string
    {
        $notFound = false;
        $data = $this->appCache->get('media_dl_' . $token, function (ItemInterface $item) use (&$notFound) {
            $notFound = true;
            $item->expiresAfter(0);
            return null;
        });
        if ($notFound || $data === null) {
            throw new \InvalidArgumentException('Invalid or expired download token');
        }
        $this->appCache->delete('media_dl_' . $token);

        $media = $this->repository->find($data['id']);
        if (!$media) {
            throw new \InvalidArgumentException('Media not found');
        }
        $sizeMb = (int) ceil((int) $media->getSize() / 1_048_576);
        $this->capacityService->assertAllowed(CapacityType::NetworkBandwidth, $sizeMb);
        $this->configService->incrementBandwidth((int) $media->getSize());
        $this->capacityService->recordUsage(CapacityType::NetworkBandwidth, $sizeMb);
        return $this->resolveDownloadUrlForPath($data['path'], $media);
    }

    public function resolveDownloadUrl(Media $media): string
    {
        return $this->resolveDownloadUrlForPath($media->getPath(), $media);
    }

    private function resolveDownloadUrlForPath(string $path, ?Media $media = null): string
    {
        // Only attempt backup if we know it was uploaded there; skip the HEAD check otherwise
        $tryBackup = $media === null || $media->isBackupSuccess() === true;

        if ($tryBackup) {
            $backupUrl = $this->getBackupFileUrl($path);
            try {
                $head = $this->httpClient->request('HEAD', $backupUrl, ['timeout' => 2]);
                if ($head->getStatusCode() === 200) {
                    return $backupUrl;
                }
            } catch (\Throwable) {}
        }

        return $this->getS3DownloadUrl($path);
    }

    private function generateStoragePath(string $filename): string
    {
        $org = $this->configService->getConfigValue('organization', isJson: true) ?? [];
        $prefix = $org['token'] ?? 'default';
        $hash = bin2hex(random_bytes(8));
        $sanitized = $this->sanitizeFilename($filename);
        return sprintf('%s/%s/%s/%s/%s', $prefix, substr($hash, 0, 2), substr($hash, 2, 2), substr($hash, 4), $sanitized);
    }

    private function getS3Client(): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => $this->params->get('aws.s3.region'),
            'credentials' => [
                'key' => $this->params->get('aws.s3.key'),
                'secret' => $this->params->get('aws.s3.secret'),
            ],
        ]);
    }

    public function syncMediaToBackup(Media $media): void
    {
        $content = $this->mediaStorage->read($media->getPath());
        $backupBase = rtrim($this->params->get('backup_media_url'), '/');
        $presignResp = $this->httpClient->request('POST', $backupBase . '/presign', [
            'headers' => ['X-Api-Key' => $this->params->get('backup_media_api_key')],
            'json'    => ['path' => $media->getPath()],
        ]);
        $backupUploadUrl = $backupBase . $presignResp->toArray()['uploadUrl'];
        $this->httpClient->request('PUT', $backupUploadUrl, [
            'body'    => $content,
            'headers' => ['Content-Type' => $media->getMime() ?? 'application/octet-stream'],
        ]);
        $media->setBackupSuccess(true);
        $this->repository->save($media);
    }

    public function syncMediaToS3(Media $media): void
    {
        $backupUrl = $this->getBackupFileUrl($media->getPath());
        $response  = $this->httpClient->request('GET', $backupUrl, ['timeout' => 30]);
        $content   = $response->getContent();
        $this->mediaStorage->write($media->getPath(), $content);
        $media->setS3Success(true);
        $this->repository->save($media);
    }

    function sanitizeFilename(string $filename): string
    {
        // Remove characters that are generally illegal on most file systems
        // This includes control characters (chr(0) to chr(31)) and common reserved characters
        $illegalChars = array_merge(
            array_map('chr', range(0, 31)), // Control characters
            ['<', '>', ':', '"', '/', '\\', '|', '?', '*'] // Common reserved characters
        );
        $filename = str_replace($illegalChars, '', $filename);

        // Replace spaces and other potentially problematic characters with a hyphen or underscore
        $filename = preg_replace('/[^a-zA-Z0-9\.\-_]/', '-', $filename);

        // Remove multiple consecutive hyphens or underscores
        $filename = preg_replace('/[-_]{2,}/', '-', $filename);

        // Trim leading/trailing hyphens or underscores
        $filename = trim($filename, '-_');

        // Handle reserved names on Windows (e.g., CON, PRN, AUX, NUL, COM1, LPT1)
        // This is a basic check; a more comprehensive list might be needed for full coverage.
        $reservedNames = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        if (in_array(strtoupper($baseName), $reservedNames)) {
            $filename = 'safe-' . $filename; // Prepend 'safe-' to avoid conflict
        }

        // Ensure maximum filename length (e.g., 255 bytes for many systems)
        // This example truncates the base name if it exceeds a limit, preserving the extension.
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $maxNameLength = 255 - (empty($ext) ? 0 : strlen($ext) + 1); // Account for extension and dot
        if (mb_strlen($nameWithoutExt, 'UTF-8') > $maxNameLength) {
            $nameWithoutExt = mb_strcut($nameWithoutExt, 0, $maxNameLength, 'UTF-8');
            $filename = $nameWithoutExt . (empty($ext) ? '' : '.' . $ext);
        }

        return $filename;
    }

}
