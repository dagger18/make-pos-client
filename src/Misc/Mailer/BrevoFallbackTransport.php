<?php

namespace App\Misc\Mailer;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BrevoFallbackTransport
{
    private bool $brevoBlockedThisRequest = false;

    private EsmtpTransport $brevoTransport;
    private EsmtpTransport $privateTransport;

    public function __construct(
        private readonly CacheInterface $codeCache,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        string $brevoSmtpUser,
        string $brevoSmtpPassword,
        private readonly string $brevoApiKey,
        string $privateSmtpHost,
        int $privateSmtpPort,
        string $privateSmtpUser,
        string $privateSmtpPassword,
    ) {
        $this->brevoTransport = new EsmtpTransport('smtp-relay.brevo.com', 587, false, null, $logger);
        $this->brevoTransport->setUsername($brevoSmtpUser);
        $this->brevoTransport->setPassword($brevoSmtpPassword);

        $this->privateTransport = new EsmtpTransport($privateSmtpHost, $privateSmtpPort, false, null, $logger);
        $this->privateTransport->setUsername($privateSmtpUser);
        $this->privateTransport->setPassword($privateSmtpPassword);
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($this->isBrevoAvailable()) {
            try {
                return $this->brevoTransport->send($message, $envelope);
            } catch (TransportException $e) {
                if ($this->isRateLimitError($e)) {
                    $this->logger->warning('Brevo rate limit hit, switching to private SMTP', [
                        'error' => $e->getMessage(),
                    ]);
                    $this->markBrevoUnavailable();
                } else {
                    throw $e;
                }
            }
        }

        $this->logger->info('Sending via private SMTP (Brevo unavailable)');
        return $this->privateTransport->send($message, $envelope);
    }

    private function isBrevoAvailable(): bool
    {
        if ($this->brevoBlockedThisRequest) {
            return false;
        }

        return (bool) $this->codeCache->get('brevo_transport_available', function (ItemInterface $item) {
            $item->expiresAfter(300); // re-check every 5 minutes
            return $this->checkBrevoCredits();
        });
    }

    private function checkBrevoCredits(): bool
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.brevo.com/v3/account', [
                'headers' => ['api-key' => $this->brevoApiKey],
                'timeout' => 3,
            ]);
            $data = $response->toArray();
            foreach ($data['plan'] ?? [] as $plan) {
                if (isset($plan['credits'], $plan['creditsUsed'])) {
                    return (int) $plan['creditsUsed'] < (int) $plan['credits'];
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Could not check Brevo credits, assuming available', [
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    private function markBrevoUnavailable(): void
    {
        $this->brevoBlockedThisRequest = true;
        $this->codeCache->delete('brevo_transport_available');
        $this->codeCache->get('brevo_transport_available', function (ItemInterface $item) {
            $item->expiresAfter(3600); // re-try Brevo after 1 hour
            return false;
        });
    }

    private function isRateLimitError(TransportException $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, '429')
            || str_contains($msg, 'rate limit')
            || str_contains($msg, 'quota exceeded')
            || str_contains($msg, 'daily limit');
    }
}
