<?php
namespace App\Module\Core\Service;

use App\Misc\Enum\CapacityType;
use App\Module\Core\Service\BaseService;

use App\Module\Notification\Entity\Mail;
use App\Module\Core\Entity\Media;
use App\Misc\Mailer\BrevoFallbackTransport;
use App\Module\Core\Enum\EntityType;
use App\Module\Notification\Enum\MailStatus;
use App\Misc\Exception\BadRequestException;
use App\Module\Notification\Repository\MailRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\MimeTypes;
use Twig\Environment;

/**
 * Class MailService
 * @package App\Service
 */
class MailService extends BaseService
{
    /**
     * MailService constructor.
     */
    public function __construct(
        protected BaseService $baseService,
        protected MailerInterface $mailer,
        protected Environment $twig,
        protected LoggerInterface $logger,
        public MailRepository $repository,
        protected BodyRendererInterface $bodyRenderer,
        private readonly BrevoFallbackTransport $brevoFallbackTransport,
        private readonly string $defaultFromAddress,
        private readonly CapacityService $capacityService,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function send($to, $subject, $template, $data) {
        $this->capacityService->assertAllowed(CapacityType::EmailSend);
        $email = (new TemplatedEmail())
            ->from(new Address($this->defaultFromAddress, 'MakeCargo Team'))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($data);

        $this->bodyRenderer->render($email);
        $this->brevoFallbackTransport->send($email);
        $this->capacityService->recordUsage(CapacityType::EmailSend);
    }
    public function sendUsingUserSmtp($to, $subject, $template, $data,EntityType $parentType, $parentId, ?Media $attachment = null) {
        $user = $this->getUser();
        if(empty($user->getSmtpServer())
            || empty($user->getSmtpPort())
            || empty($user->getSmtpUsername())
            || empty($user->getSmtpPassword())
        ) {
            throw new BadRequestException(BadRequestException::SEND_MAIL_ERROR);
        }
        $password = $this->commonService->decrypt($user->getSmtpPassword());
        $transport = new EsmtpTransport($user->getSmtpServer(),(int) $user->getSmtpPort());
        //$transport->setAuthenticators([new PlainAuthenticator()]);
        $transport->setUsername($user->getSmtpUsername());
        $transport->setPassword($password);

        $email = (new TemplatedEmail())
        ->sender($user->getSmtpUsername())
        ->to($to)
        ->subject($subject)
        ->htmlTemplate($template)
        ->context($data);
        // attach file
        if(!is_null($attachment)) {
            $stream = $this->mediaStorage->readStream($attachment->getPath());
            $email->attach($stream, $attachment->getName(), $attachment->getMime());
        }
        $this->bodyRenderer->render($email);
        $mailEntity = new Mail();
        $mailEntity->setCreatedBy($user);
        $mailEntity->setFromAddress($user->getSmtpUsername());
        $mailEntity->setToAddress($to);
        $mailEntity->setTitle($subject);
        $mailEntity->setStatus(MailStatus::Pending);
        $mailEntity->setParentType($parentType);
        $mailEntity->setParentId($parentId);

        try{
            $transport->send($email);
            $mailEntity->setStatus(MailStatus::Sent);
            $this->repository->save($mailEntity);
            return true;
        } catch(TransportException $e) {
            $mailEntity->setStatus(MailStatus::Failed);
            $this->repository->save($mailEntity);
            throw new BadRequestException(BadRequestException::SEND_MAIL_ERROR);
        }
        return false;
    }
    public function testSmtp($host, $port, $username, $password) {
        $stream = new SocketStream();
        $transport = new EsmtpTransport($host,(int) $port, false, null, $this->logger, $stream);
        //$transport->setAuthenticators([new PlainAuthenticator()]);
        $transport->setUsername($username);
        $transport->setPassword($password);
        $stream->initialize();
        try{
            $transport->start();
            $transport->stop();
            return true;
        } catch(TransportException $e) {
            return false;
        }

    }

    public function sendRaw(string $to, string $subject, string $htmlBody): void
    {
        $email = (new \Symfony\Component\Mime\Email())
            ->from(new Address($this->defaultFromAddress, 'MakeCargo Team'))
            ->to($to)
            ->subject($subject)
            ->html($htmlBody);
        $this->brevoFallbackTransport->send($email);
    }
}
