<?php
namespace App\Module\Finance\Service;

use App\Module\Finance\Entity\EbitNote;
use App\Module\Finance\Enum\EbitNoteType;
use App\Module\Finance\Repository\EbitNoteRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Stub service — EbitNoteService was removed during freight-to-POS migration.
 * Methods are no-ops until POS-specific finance logic is implemented.
 */
class EbitNoteService
{
    public EbitNoteRepository $repository;

    public function __construct(EbitNoteRepository $repository)
    {
        $this->repository = $repository;
    }

    public function setCode(EbitNote $entity): void {}

    public function checkRecord(EbitNote $entity): void {}

    public function checkParentRecord(EbitNote $entity): void {}

    public function makeInvoice(EbitNote $entity, Request $request): void {}

    public function cancelAllInvoices(EbitNote $entity, Request $request): void {}

    public function sendEmail(EbitNote $entity, Request $request): void {}

    public function downloadMonthlyPdf(Request $request): object
    {
        throw new \LogicException('downloadMonthlyPdf not yet implemented for POS.');
    }

    public function updateExchangeRate(EbitNote $entity, array $exchangeRates): void {}

    public function replicate(EbitNote $source, EbitNoteType $type, array $overrides = [], bool $save = true): EbitNote
    {
        throw new \LogicException('replicate not yet implemented for POS.');
    }
}
