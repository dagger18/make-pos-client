<?php

namespace App\Misc;

use Aws\S3\S3Client;
use Liip\ImagineBundle\Binary\Loader\LoaderInterface;
use Liip\ImagineBundle\Model\Binary;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RemoteStreamLoader implements LoaderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ParameterBagInterface $params,
        private readonly S3Client $s3Client,
    ) {}

    public function find($path): Binary
    {
        $backupUrl = rtrim($this->params->get('backup_media_url'), '/') . '/file/' . $path;
        try {
            $response = $this->httpClient->request('GET', $backupUrl, ['timeout' => 5]);
            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                return $this->makeBinary($content);
            }
        } catch (\Throwable) {}

        $result = $this->s3Client->getObject([
            'Bucket' => $this->params->get('aws.s3.bucket'),
            'Key'    => $path,
        ]);
        $content = (string) $result['Body'];
        return $this->makeBinary($content);
    }

    private function makeBinary(string $content): Binary
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: 'image/jpeg';
        $format = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpeg',
            'image/png'               => 'png',
            'image/gif'               => 'gif',
            'image/webp'              => 'webp',
            'image/bmp'               => 'bmp',
            'image/avif'              => 'avif',
            default                   => 'jpeg',
        };
        return new Binary($content, $mime, $format);
    }
}
