<?php

namespace App\Module\Finance\Controller;

use App\Module\Finance\Entity\EbitNote;

use App\Module\Core\Controller\CrudController;

use App\Module\Crm\Entity\Client;
use App\Module\Core\Entity\Money;
use App\Module\Core\Entity\User;
use App\Misc\Attribute\Log;
use App\Module\Finance\Enum\EbitNoteStatus;
use App\Module\Finance\Enum\EbitNoteType;
use App\Module\Finance\Enum\VarianceStatus;
use App\Misc\NumberToWords\NumberToWords;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Module\Crm\Repository\ClientRepository;
use App\Module\Core\Repository\MediaRepository;
use App\Resolver\CrudEntityValueResolver;
use App\Resolver\CrudRequestPayloadValueResolver;
use App\Module\Core\Service\BaseService;
use App\Module\Finance\Service\EbitNoteService;
use App\Module\Finance\Service\FxGainLossService;
use App\Module\Finance\Service\JournalPostingService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ebit-note')]
#[IsGranted('ROLE_USER')]
#[AppModule('finance')]
class EbitNoteController extends CrudController
{
    use GetActionTrait;
    use PutActionTrait;

    public function __construct(
        protected BaseService $baseService,
        protected EbitNoteService $ebitNoteService,
        protected FxGainLossService $fxGainLossService,
        protected JournalPostingService $journalPostingService,
        protected ClientRepository $clientRepository,
        protected MediaRepository $mediaRepository,
    ) {}

    #[Route('', methods: ['POST'])]
    #[IsGranted('POST', 'entity')]
    #[Log]
    public function POST(
        #[MapRequestPayload(resolver: CrudRequestPayloadValueResolver::class)]
        $entity,
        #[CurrentUser] User $user,
        Request $request
    ): JsonResponse {
        if (method_exists($entity, 'setCreatedBy')) {
            $entity->setCreatedBy($user);
        }
        if (
            $entity->getType() === EbitNoteType::POBO
            || $entity->getType() === EbitNoteType::COBO
        ) {
            $entity->setStatus(EbitNoteStatus::Active);
        } else {
            $entity->setStatus(EbitNoteStatus::Pending);
        }
        $this->ebitNoteService->setCode($entity);
        if (
            $entity->getType() === EbitNoteType::RecordReceipt
            || $entity->getType() === EbitNoteType::RecordPayment
        ) {
            $this->fxGainLossService->compute($entity);
        }
        $result = $this->repository->save($entity, $request);
        $this->ebitNoteService->checkRecord($entity);
        $this->postJournalIfNeeded($entity);
        return $this->json($result, Response::HTTP_CREATED);
    }

    #[Route('/make-invoice/{id}', methods: ['POST'])]
    #[IsGranted('Add', 'entity')]
    #[Log]
    public function makeInvoice(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        Request $request
    ): JsonResponse {
        $this->ebitNoteService->makeInvoice($entity, $request);
        return $this->json([], Response::HTTP_CREATED);
    }

    #[Route('/cancel-all-invoices/{id}', methods: ['POST'])]
    #[IsGranted('Add', 'entity')]
    #[Log]
    public function cancelAllInvoices(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        Request $request
    ): JsonResponse {
        $this->ebitNoteService->cancelAllInvoices($entity, $request);
        return $this->json([], Response::HTTP_CREATED);
    }

    #[Route('/get-amount-in-words/{currency}/{amount}', methods: ['GET'])]
    public function getAmountInWords(string $currency, int $amount): JsonResponse
    {
        $langMap = ['VND' => 'vi'];
        $words = NumberToWords::transformCurrency($langMap[$currency] ?? 'en', $amount, $currency);
        return $this->json(['words' => $words]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('DELETE', 'entity')]
    #[Log]
    public function DELETE(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        int $id,
        Request $request
    ): JsonResponse {
        if (!$entity) {
            throw $this->createNotFoundException('No entity found for id ' . $id);
        }
        if (
            $entity->getType() === EbitNoteType::InvoiceCredit
            || $entity->getType() === EbitNoteType::InvoiceDebit
        ) {
            $this->ebitNoteService->cancelAllInvoices($entity->getParent(), $request);
            return $this->json([]);
        }
        $shouldCheckParent = !in_array($entity->getType(), [
            EbitNoteType::RecordPayment,
            EbitNoteType::RecordReceipt,
            EbitNoteType::POBO,
            EbitNoteType::COBO,
        ]);
        $parentEntity = $entity->getParentNote();

        $this->repository->delete($entity);

        if ($shouldCheckParent && $parentEntity) {
            $this->ebitNoteService->checkParentRecord($parentEntity);
        }
        return $this->json([]);
    }

    #[Route('/send-email/{id}', methods: ['POST'])]
    #[Log]
    public function sendEmail(
        #[MapEntity(resolver: CrudEntityValueResolver::class)]
        $entity,
        Request $request
    ): JsonResponse {
        $this->ebitNoteService->sendEmail($entity, $request);
        return $this->json([], Response::HTTP_CREATED);
    }

    #[Route('/download-monthly-pdf', methods: ['POST'])]
    #[Log]
    public function downloadMonthlyPdf(Request $request): JsonResponse
    {
        $zip = $this->ebitNoteService->downloadMonthlyPdf($request);
        return $this->json(['zip' => $zip->getId()], Response::HTTP_CREATED);
    }

    #[Route('/update-exchange-rates', methods: ['POST'])]
    #[IsGranted('Read', 'request')]
    #[Log]
    public function updateExchangeRates(Request $request): JsonResponse
    {
        $data = $request->request->all();
        foreach ($data['ids'] as $id) {
            $ebitNote = $this->ebitNoteService->repository->find($id);
            $this->ebitNoteService->updateExchangeRate($ebitNote, $data['exchangeRates']);
            $this->ebitNoteService->updateExchangeRate($ebitNote->getParentNote(), $data['exchangeRates']);
        }
        return $this->json(['result' => 'success'], Response::HTTP_CREATED);
    }

    #[Route('/mark-invoice-debit', methods: ['POST'])]
    #[IsGranted('Read', 'request')]
    #[Log]
    public function markPaidInvoiceDebit(Request $request): JsonResponse
    {
        $data = $request->request->all();
        $status = EbitNoteStatus::from($data['status']);
        $recalculateClients = [];
        foreach ($data['ids'] as $id) {
            $ebitNote = $this->ebitNoteService->repository->find($id);
            if ($ebitNote->getStatus() === $status) continue;
            $ebitNote->setStatus($status);
            $ebitNote->getParentNote()->setStatus($status);
            $this->repository->save($ebitNote);
            if ($status === EbitNoteStatus::Done) {
                $paymentRecord = $this->ebitNoteService->replicate($ebitNote, EbitNoteType::RecordReceipt, [], false);
                $this->ebitNoteService->setCode($paymentRecord);
                $this->repository->save($paymentRecord);
                $this->mediaRepository->deleteMediaRelation($paymentRecord->getId());
            }
            if ($status === EbitNoteStatus::Active) {
                $this->repository->deletePaymentRecord($ebitNote->getId());
            }
            $recalculateClients[$ebitNote->getCollectFrom()->getId()] = $ebitNote->getCollectFrom();
        }
        /** @var Client $client */
        foreach ($recalculateClients as $client) {
            $unpaid = new Money($this->repository->getClientUnpaidTotalUSD($client), 'USD', 1);
            $client->setUnpaid($unpaid);
            $this->clientRepository->save($client);
        }
        return $this->json(['result' => 'success'], Response::HTTP_CREATED);
    }

    #[Route('/{id}/approve-variance', methods: ['POST'])]
    #[IsGranted('Add', 'entity')]
    #[Log]
    public function approveVariance(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $note = $this->repository->find($id);
        if (!$note) throw $this->createNotFoundException();
        $note->setVarianceStatus(VarianceStatus::Approved)
             ->setApprovedBy($user)
             ->setApprovedAt(new \DateTimeImmutable());
        $this->repository->save($note);
        return $this->json(['varianceStatus' => $note->getVarianceStatus()->value]);
    }

    #[Route('/{id}/dispute-variance', methods: ['POST'])]
    #[IsGranted('Add', 'entity')]
    #[Log]
    public function disputeVariance(int $id): JsonResponse
    {
        $note = $this->repository->find($id);
        if (!$note) throw $this->createNotFoundException();
        $note->setVarianceStatus(VarianceStatus::Disputed)
             ->setApprovedBy(null)
             ->setApprovedAt(null);
        $this->repository->save($note);
        return $this->json(['varianceStatus' => $note->getVarianceStatus()->value]);
    }

    #[Route('/{id}/lock', methods: ['POST'])]
    #[IsGranted('Add', 'entity')]
    #[Log]
    public function lock(int $id): JsonResponse
    {
        $note = $this->repository->find($id);
        if (!$note) throw $this->createNotFoundException();
        if ($note->isLocked()) {
            return $this->json(['error' => $this->trans('Already locked.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $note->setIsLocked(true);
        $this->repository->save($note);
        return $this->json(['isLocked' => true]);
    }

    private function postJournalIfNeeded(EbitNote $note): void
    {
        match ($note->getType()) {
            EbitNoteType::InvoiceDebit  => $this->journalPostingService->postArInvoice($note),
            EbitNoteType::InvoiceCredit => $this->journalPostingService->postApBill($note),
            EbitNoteType::RecordReceipt => $this->journalPostingService->postArPayment($note),
            EbitNoteType::RecordPayment => $this->journalPostingService->postApPayment($note),
            EbitNoteType::Credit        => $this->journalPostingService->postCreditNote($note),
            default => null,
        };
    }
}
