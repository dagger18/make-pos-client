<?php
declare(strict_types=1);
namespace App\Module\Compliance\Service;

use App\Module\Crm\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;

class GdprService
{
    public function __construct(
        private readonly ContactRepository      $contactRepo,
        private readonly AuditService           $auditService,
        private readonly EntityManagerInterface $em,
    ) {}

    public function handleErasureRequest(string $email, string $requestRef, ?int $requestorId = null): array
    {
        $this->auditService->log(
            eventType:    'COMPLIANCE.GDPR_REQUEST',
            actorType:    'USER',
            actorId:      $requestorId,
            objectType:   'contact',
            objectRef:    $email,
            actionDetail: ['type' => 'ERASURE', 'ref' => $requestRef],
        );

        $contact = $this->contactRepo->findOneBy(['email' => $email]);
        if (!$contact) {
            return ['status' => 'NOT_FOUND', 'email' => $email];
        }

        $contactId = $contact->getId();

        $contact->setFirstName('ERASED');
        if (method_exists($contact, 'setLastName')) {
            $contact->setLastName('ERASED');
        }
        $contact->setEmail('erased_' . $contactId . '@deleted.invalid');
        if (method_exists($contact, 'setPhone')) $contact->setPhone(null);
        if (method_exists($contact, 'setMobile')) $contact->setMobile(null);
        if (method_exists($contact, 'setIsActive')) $contact->setIsActive(false);
        $this->em->flush();

        $this->auditService->log(
            eventType:    'COMPLIANCE.DATA_ERASED',
            actorType:    'USER',
            actorId:      $requestorId,
            objectType:   'contact',
            objectId:     $contactId,
            objectRef:    $email,
            actionDetail: [
                'erased'     => ['firstName', 'lastName', 'email', 'phone', 'mobile'],
                'requestRef' => $requestRef,
            ],
        );

        return [
            'status'    => 'ERASED',
            'contactId' => $contactId,
            'erased'    => ['firstName', 'lastName', 'email', 'phone', 'mobile'],
            'retained'  => ['jobPartyRecords', 'invoiceContacts'],
            'reason'    => 'Financial and operational records retained per legal requirement',
        ];
    }

    public function exportSubjectData(int $contactId): array
    {
        $contact = $this->contactRepo->find($contactId);
        if (!$contact) {
            return ['status' => 'NOT_FOUND'];
        }

        $this->auditService->log(
            eventType:    'COMPLIANCE.GDPR_REQUEST',
            actorType:    'USER',
            objectType:   'contact',
            objectId:     $contactId,
            actionDetail: ['type' => 'ACCESS'],
        );

        return [
            'status'    => 'OK',
            'contact'   => [
                'id'        => $contact->getId(),
                'firstName' => method_exists($contact, 'getFirstName') ? $contact->getFirstName() : null,
                'lastName'  => method_exists($contact, 'getLastName') ? $contact->getLastName() : null,
                'email'     => $contact->getEmail(),
                'phone'     => method_exists($contact, 'getPhone') ? $contact->getPhone() : null,
                'mobile'    => method_exists($contact, 'getMobile') ? $contact->getMobile() : null,
            ],
        ];
    }
}
