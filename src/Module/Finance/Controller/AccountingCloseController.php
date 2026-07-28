<?php
namespace App\Module\Finance\Controller;

use App\Module\Finance\Entity\EbitNote;
use App\Module\Finance\Entity\JournalEntry;
use App\Module\Finance\Repository\EbitNoteRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report')]
#[IsGranted('ROLE_USER')]
#[AppModule('finance')]
class AccountingCloseController extends AbstractController
{
    public function __construct(
        private readonly ShipmentRepository    $shipmentRepo,
        private readonly EbitNoteRepository    $ebitNoteRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/accounting-close/{shipmentId}/checklist', methods: ['GET'])]
    public function checklist(int $shipmentId): JsonResponse
    {
        $shipment = $this->shipmentRepo->find($shipmentId);
        if (!$shipment) {
            return $this->json(['error' => $this->trans('Shipment not found')], Response::HTTP_NOT_FOUND);
        }

        $em = $this->em;

        // --- 1. AP check: IC notes without MATCHED or APPROVED status ---
        $unmatchedApNotes = $em->createQuery(
            'SELECT en.id, en.code, en.varianceStatus
             FROM App\Module\Finance\Entity\EbitNote en
             WHERE en.shipment = :sid
               AND en.type = :ic
               AND en.status != :done
               AND (en.varianceStatus IS NULL
                    OR en.varianceStatus NOT IN (:ok))'
        )
            ->setParameter('sid', $shipmentId)
            ->setParameter('ic', 'IC')
            ->setParameter('done', 'D')
            ->setParameter('ok', ['MATCHED', 'APPROVED'])
            ->getArrayResult();

        $totalApNotes = (int) $em->createQuery(
            'SELECT COUNT(en.id) FROM App\Module\Finance\Entity\EbitNote en
             WHERE en.shipment = :sid AND en.type = :ic AND en.status != :done'
        )
            ->setParameter('sid', $shipmentId)
            ->setParameter('ic', 'IC')
            ->setParameter('done', 'D')
            ->getSingleScalarResult();

        // --- 2. AR check: ID notes that have no RPT (receipt) children ---
        $unsettledArNotes = $em->createQuery(
            'SELECT en.id, en.code
             FROM App\Module\Finance\Entity\EbitNote en
             WHERE en.shipment = :sid
               AND en.type = :id
               AND en.status != :done
               AND (SELECT COUNT(r.id) FROM App\Module\Finance\Entity\EbitNote r
                    WHERE r.parentNote = en AND r.type = :rpt) = 0'
        )
            ->setParameter('sid', $shipmentId)
            ->setParameter('id', 'ID')
            ->setParameter('done', 'D')
            ->setParameter('rpt', 'RPT')
            ->getArrayResult();

        $totalArNotes = (int) $em->createQuery(
            'SELECT COUNT(en.id) FROM App\Module\Finance\Entity\EbitNote en
             WHERE en.shipment = :sid AND en.type = :id AND en.status != :done'
        )
            ->setParameter('sid', $shipmentId)
            ->setParameter('id', 'ID')
            ->setParameter('done', 'D')
            ->getSingleScalarResult();

        // --- 3. Journal check: unposted journal entries linked to this shipment's notes ---
        $unpostedJournals = $em->createQuery(
            'SELECT j.id, j.journalNumber, j.sourceType
             FROM App\Module\Finance\Entity\JournalEntry j
             JOIN j.ebitNote en
             WHERE en.shipment = :sid AND j.isPosted = false'
        )
            ->setParameter('sid', $shipmentId)
            ->getArrayResult();

        $totalJournals = (int) $em->createQuery(
            'SELECT COUNT(j.id) FROM App\Module\Finance\Entity\JournalEntry j
             JOIN j.ebitNote en WHERE en.shipment = :sid'
        )
            ->setParameter('sid', $shipmentId)
            ->getSingleScalarResult();

        $checks = [
            [
                'code'    => 'ap_matched',
                'label'   => 'All AP bills matched or approved',
                'passed'  => count($unmatchedApNotes) === 0,
                'total'   => $totalApNotes,
                'failing' => count($unmatchedApNotes),
                'items'   => $unmatchedApNotes,
            ],
            [
                'code'    => 'ar_settled',
                'label'   => 'All AR invoices have at least one payment recorded',
                'passed'  => count($unsettledArNotes) === 0,
                'total'   => $totalArNotes,
                'failing' => count($unsettledArNotes),
                'items'   => $unsettledArNotes,
            ],
            [
                'code'    => 'journals_posted',
                'label'   => 'All journal entries posted',
                'passed'  => count($unpostedJournals) === 0,
                'total'   => $totalJournals,
                'failing' => count($unpostedJournals),
                'items'   => $unpostedJournals,
            ],
        ];

        $allPassed = array_reduce($checks, fn($carry, $c) => $carry && $c['passed'], true);

        return $this->json([
            'shipmentId'         => $shipmentId,
            'accountingClosedAt' => $shipment->getAccountingClosedAt()?->format('Y-m-d H:i:s'),
            'allPassed'          => $allPassed,
            'checks'             => $checks,
        ]);
    }

    #[Route('/accounting-close/{shipmentId}', methods: ['POST'])]
    public function accountingClose(int $shipmentId): JsonResponse
    {
        $shipment = $this->shipmentRepo->find($shipmentId);
        if (!$shipment) {
            return $this->json(['error' => $this->trans('Shipment not found')], Response::HTTP_NOT_FOUND);
        }
        if ($shipment->getAccountingClosedAt()) {
            return $this->json(['error' => $this->trans('Already closed')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $notes = $this->ebitNoteRepo->findBy(['shipment' => $shipment]);
        foreach ($notes as $note) {
            $note->setIsLocked(true);
        }

        $shipment->setAccountingClosedAt(new \DateTimeImmutable());
        $this->shipmentRepo->save($shipment);

        return $this->json(['accountingClosedAt' => $shipment->getAccountingClosedAt()->format('Y-m-d H:i:s')]);
    }
}
