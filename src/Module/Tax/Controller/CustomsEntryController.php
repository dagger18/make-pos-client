<?php

declare(strict_types=1);

namespace App\Module\Tax\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Core\Service\MasterSyncService;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Tax\Entity\CustomsEntry;
use App\Module\Tax\Entity\CustomsEntryLine;
use App\Module\Tax\Repository\CustomsEntryLineRepository;
use App\Module\Tax\Repository\CustomsEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/customs-entries')]
#[IsGranted('ROLE_USER')]
#[AppModule('tax')]
class CustomsEntryController extends AbstractController
{
    public function __construct(
        private readonly CustomsEntryRepository     $entryRepository,
        private readonly CustomsEntryLineRepository $lineRepository,
        private readonly ShipmentRepository         $shipmentRepository,
        private readonly MasterSyncService          $masterSyncService,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        $entries = $this->entryRepository->findByShipment($shipmentId);

        return $this->json(array_map(fn($e) => $this->serializeEntry($e), $entries));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) {
            throw $this->createNotFoundException();
        }

        $body = json_decode($request->getContent(), true) ?? [];

        if (empty($body['entryType'])) {
            return $this->json(['error' => $this->trans('entryType is required.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entry = new CustomsEntry();
        $entry->setShipment($shipment);
        $this->hydrateEntry($entry, $body);

        $this->entryRepository->save($entry, true);

        return $this->json($this->serializeEntry($entry), Response::HTTP_CREATED);
    }

    #[Route('/{entryId}', methods: ['PATCH'])]
    public function update(int $shipmentId, int $entryId, Request $request): JsonResponse
    {
        $entry = $this->findEntry($shipmentId, $entryId);
        $body  = json_decode($request->getContent(), true) ?? [];

        $this->hydrateEntry($entry, $body);

        $this->entryRepository->save($entry, true);

        return $this->json($this->serializeEntry($entry));
    }

    #[Route('/{entryId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $entryId): JsonResponse
    {
        $entry = $this->findEntry($shipmentId, $entryId);

        if ($entry->getStatus() !== 'DRAFT') {
            return $this->json(['error' => $this->trans('Only DRAFT entries can be deleted.')], Response::HTTP_FORBIDDEN);
        }

        $this->entryRepository->remove($entry, true);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{entryId}/submit', methods: ['POST'])]
    public function submit(int $shipmentId, int $entryId): JsonResponse
    {
        $entry = $this->findEntry($shipmentId, $entryId);

        if (!in_array($entry->getStatus(), ['DRAFT', 'REJECTED'], true)) {
            return $this->json(['error' => $this->trans('Only DRAFT or REJECTED entries can be submitted.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->masterSyncService->submitCustomsDeclaration($entry);

        if (!empty($result['declaration_number'])) {
            $entry->setDeclarationNumber($result['declaration_number']);
            $entry->setStatus($result['status'] ?? 'SUBMITTED');
            $entry->setSubmissionRef($result['submission_ref'] ?? null);
            $entry->setSubmittedAt(new \DateTime());
        }

        $this->entryRepository->save($entry, true);

        return $this->json($this->serializeEntry($entry));
    }

    #[Route('/{entryId}/sync-status', methods: ['POST'])]
    public function syncStatus(int $shipmentId, int $entryId): JsonResponse
    {
        $entry = $this->findEntry($shipmentId, $entryId);

        if ($entry->getDeclarationNumber() === null) {
            return $this->json(['error' => $this->trans('No declaration number yet.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->masterSyncService->checkCustomsStatus(
            $entry->getDeclarationNumber(),
            $entry->getSystemCode() ?? ''
        );

        $newStatus = $result['status'] ?? null;
        if ($newStatus) {
            $entry->setStatus($newStatus);
        }
        if ($newStatus === 'RELEASED' && !$entry->getReleasedAt()) {
            $entry->setReleasedAt(new \DateTime());
        }
        if ($newStatus === 'ACKNOWLEDGED' && !$entry->getAcknowledgedAt()) {
            $entry->setAcknowledgedAt(new \DateTime());
        }
        if (isset($result['total_duty'])) {
            $entry->setTotalDuty((string) $result['total_duty']);
        }

        $this->entryRepository->save($entry, true);

        return $this->json($this->serializeEntry($entry));
    }

    #[Route('/{entryId}/lines', methods: ['POST'])]
    public function addLine(int $shipmentId, int $entryId, Request $request): JsonResponse
    {
        $entry = $this->findEntry($shipmentId, $entryId);
        $body  = json_decode($request->getContent(), true) ?? [];

        $line = new CustomsEntryLine();
        $line->setCustomsEntry($entry);

        $maxLine = $this->lineRepository->createQueryBuilder('l')
            ->select('MAX(l.lineNumber)')
            ->where('l.customsEntry = :e')
            ->setParameter('e', $entry)
            ->getQuery()->getSingleScalarResult();

        $line->setLineNumber(isset($body['lineNumber']) ? (int) $body['lineNumber'] : (int) ($maxLine ?? 0) + 1);

        $this->hydrateLine($line, $body);

        $this->lineRepository->save($line, true);

        return $this->json($this->serializeLine($line), Response::HTTP_CREATED);
    }

    #[Route('/{entryId}/lines/{lineId}', methods: ['PATCH'])]
    public function updateLine(int $shipmentId, int $entryId, int $lineId, Request $request): JsonResponse
    {
        $line = $this->lineRepository->find($lineId);

        if (
            !$line
            || $line->getCustomsEntry()->getId() !== $entryId
            || $line->getCustomsEntry()->getShipment()->getId() !== $shipmentId
        ) {
            throw $this->createNotFoundException();
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrateLine($line, $body);

        $this->lineRepository->save($line, true);

        return $this->json($this->serializeLine($line));
    }

    #[Route('/{entryId}/lines/{lineId}', methods: ['DELETE'])]
    public function deleteLine(int $shipmentId, int $entryId, int $lineId): JsonResponse
    {
        $line = $this->lineRepository->find($lineId);

        if (
            !$line
            || $line->getCustomsEntry()->getId() !== $entryId
            || $line->getCustomsEntry()->getShipment()->getId() !== $shipmentId
        ) {
            throw $this->createNotFoundException();
        }

        $this->lineRepository->remove($line, true);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findEntry(int $shipmentId, int $entryId): CustomsEntry
    {
        $entry = $this->entryRepository->find($entryId);
        if (!$entry || $entry->getShipment()->getId() !== $shipmentId) {
            throw $this->createNotFoundException();
        }

        return $entry;
    }

    private function serializeEntry(CustomsEntry $e): array
    {
        return [
            'id'                => $e->getId(),
            'entryType'         => $e->getEntryType(),
            'entryMode'         => $e->getEntryMode(),
            'declarationNumber' => $e->getDeclarationNumber(),
            'entryNumber'       => $e->getEntryNumber(),
            'status'            => $e->getStatus(),
            'customsOffice'     => $e->getCustomsOffice(),
            'countryCode'       => $e->getCountryCode(),
            'systemCode'        => $e->getSystemCode(),
            'cifValue'          => $e->getCifValue(),
            'valueCurrency'     => $e->getValueCurrency(),
            'totalDuty'         => $e->getTotalDuty(),
            'totalVat'          => $e->getTotalVat(),
            'totalTax'          => $e->getTotalTax(),
            'submittedAt'       => $e->getSubmittedAt()?->format(\DateTimeInterface::ATOM),
            'acknowledgedAt'    => $e->getAcknowledgedAt()?->format(\DateTimeInterface::ATOM),
            'releasedAt'        => $e->getReleasedAt()?->format(\DateTimeInterface::ATOM),
            'submissionRef'     => $e->getSubmissionRef(),
            'notes'             => $e->getNotes(),
            'createdAt'         => $e->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'lines'             => array_map(fn($l) => $this->serializeLine($l), $e->getLines()->toArray()),
        ];
    }

    private function serializeLine(CustomsEntryLine $l): array
    {
        return [
            'id'              => $l->getId(),
            'lineNumber'      => $l->getLineNumber(),
            'hsCode'          => $l->getHsCode(),
            'description'     => $l->getDescription(),
            'countryOfOrigin' => $l->getCountryOfOrigin(),
            'packages'        => $l->getPackages(),
            'netWeightKg'     => $l->getNetWeightKg(),
            'grossWeightKg'   => $l->getGrossWeightKg(),
            'quantity'        => $l->getQuantity(),
            'uom'             => $l->getUom(),
            'unitPrice'       => $l->getUnitPrice(),
            'lineValue'       => $l->getLineValue(),
            'valueCurrency'   => $l->getValueCurrency(),
            'dutyRate'        => $l->getDutyRate(),
            'dutyAmount'      => $l->getDutyAmount(),
            'vatRate'         => $l->getVatRate(),
            'vatAmount'       => $l->getVatAmount(),
            'isRestricted'    => $l->isRestricted(),
        ];
    }

    private function hydrateEntry(CustomsEntry $entry, array $body): void
    {
        // entryType handled first — it is non-nullable so set directly when present
        if (array_key_exists('entryType', $body) && $body['entryType'] !== '') {
            $entry->setEntryType($body['entryType']);
        }

        $strings = ['entryMode', 'declarationNumber', 'entryNumber', 'status', 'customsOffice', 'countryCode', 'systemCode', 'valueCurrency', 'submissionRef', 'notes'];
        foreach ($strings as $f) {
            if (array_key_exists($f, $body)) {
                $setter = 'set' . ucfirst($f);
                $entry->$setter($body[$f] !== '' ? $body[$f] : null);
            }
        }

        $decimals = ['cifValue', 'totalDuty', 'totalVat', 'totalTax'];
        foreach ($decimals as $f) {
            if (array_key_exists($f, $body)) {
                $setter = 'set' . ucfirst($f);
                $entry->$setter($body[$f] !== null && $body[$f] !== '' ? (string) $body[$f] : null);
            }
        }

        $datetimes = ['submittedAt', 'acknowledgedAt', 'releasedAt'];
        foreach ($datetimes as $f) {
            if (array_key_exists($f, $body)) {
                $setter = 'set' . ucfirst($f);
                $entry->$setter($body[$f] ? new \DateTime($body[$f]) : null);
            }
        }
    }

    private function hydrateLine(CustomsEntryLine $line, array $body): void
    {
        $strings  = ['hsCode', 'description', 'countryOfOrigin', 'uom', 'valueCurrency'];
        $ints     = ['packages'];
        $decimals = ['netWeightKg', 'grossWeightKg', 'quantity', 'unitPrice', 'lineValue', 'dutyRate', 'dutyAmount', 'vatRate', 'vatAmount'];

        foreach ($strings as $f) {
            if (array_key_exists($f, $body)) {
                $setter = 'set' . ucfirst($f);
                $line->$setter($body[$f] ?: null);
            }
        }
        foreach ($ints as $f) {
            if (array_key_exists($f, $body)) {
                $setter = 'set' . ucfirst($f);
                $line->$setter(isset($body[$f]) ? (int) $body[$f] : null);
            }
        }
        foreach ($decimals as $f) {
            if (array_key_exists($f, $body)) {
                $setter = 'set' . ucfirst($f);
                $line->$setter($body[$f] !== null ? (string) $body[$f] : null);
            }
        }

        if (array_key_exists('isRestricted', $body)) {
            $line->setIsRestricted((bool) $body['isRestricted']);
        }
        if (array_key_exists('lineNumber', $body)) {
            $line->setLineNumber((int) $body['lineNumber']);
        }
    }
}
