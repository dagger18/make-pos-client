<?php
namespace App\Module\Quote\Service;

use App\Module\Carrier\Entity\Provider;
use App\Module\Carrier\Repository\ProviderRepository;
use App\Module\Core\Entity\Money;
use App\Module\Core\Entity\User;
use App\Module\Core\Enum\TransportType;
use App\Module\Core\Repository\PortRepository;
use App\Module\Core\Service\BaseService;
use App\Module\Finance\Enum\ChargeType;
use App\Module\Finance\Repository\ChargeRepository;
use App\Module\Operations\Enum\ContainerType;
use App\Module\Quote\Entity\Rate;
use App\Module\Quote\Entity\RateImportJob;
use App\Module\Quote\Entity\RateImportRow;
use App\Module\Quote\Repository\RateImportJobRepository;
use App\Module\Quote\Repository\RateImportRowRepository;
use App\Module\Quote\Repository\RateRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RateImportService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public RateImportJobRepository $repository,
        private RateImportRowRepository $rowRepository,
        private RateRepository $rateRepository,
        private PortRepository $portRepository,
        private ChargeRepository $chargeRepository,
        private ProviderRepository $providerRepository,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function parseAndPreview(
        UploadedFile $file,
        string $transportType,
        ?int $providerId,
        string $currency,
        string $effectiveDate,
        string $expiryDate,
        User $user
    ): RateImportJob {
        $job = new RateImportJob();
        $job->setImportSource('EXCEL');
        $job->setTransportType(TransportType::from($transportType));
        $job->setFileName($file->getClientOriginalName());
        $job->setStatus('PARSING');
        $job->setCurrency($currency);
        $job->setEffectiveDate(new \DateTime($effectiveDate));
        $job->setExpiryDate(new \DateTime($expiryDate));
        $job->setUploadedBy($user);

        if ($providerId !== null) {
            $provider = $this->providerRepository->find($providerId);
            $job->setProvider($provider);
        }

        $em = $this->repository->getEntityManager();
        $em->persist($job);

        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();

        $colMap = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            $ci = $row->getCellIterator();
            $ci->setIterateOnlyExistingCells(false);
            foreach ($ci as $cell) {
                $val = strtoupper(trim((string) $cell->getValue()));
                if ($val !== '') {
                    $colMap[$val] = $cell->getColumn();
                }
            }
        }

        $previewRows = [];
        $errorCount = 0;
        $totalCount = 0;

        foreach ($sheet->getRowIterator(2) as $row) {
            $rowNum = $row->getRowIndex();
            $ci = $row->getCellIterator();
            $ci->setIterateOnlyExistingCells(false);
            $data = [];
            foreach ($ci as $cell) {
                $data[$cell->getColumn()] = $cell->getValue();
            }
            if (empty(array_filter($data, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }
            $totalCount++;

            $previewRow = $this->buildPreviewRow($job, $rowNum, $data, $colMap, $currency);
            if ($previewRow->getAction() === 'ERROR') {
                $errorCount++;
            }
            $previewRows[] = $previewRow;
            $em->persist($previewRow);
        }

        $job->setTotalRows($totalCount);
        $job->setRowsErrored($errorCount);
        $job->setStatus('PREVIEW');

        $em->flush();

        return $job;
    }

    private function buildPreviewRow(
        RateImportJob $job,
        int $rowNum,
        array $data,
        array $colMap,
        string $currency
    ): RateImportRow {
        $get = fn(string $key) => isset($colMap[$key]) ? trim((string) ($data[$colMap[$key]] ?? '')) : '';

        $row = new RateImportRow();
        $row->setImportJob($job);
        $row->setRowNumber($rowNum);

        $polCode       = $get('POL_CODE');
        $podCode       = $get('POD_CODE');
        $containerType = $get('CONTAINER_TYPE') ?: null;
        $chargeCode    = $get('CHARGE_CODE');
        $buyingRaw     = $get('BUYING_RATE');
        $sellingRaw    = $get('SELLING_RATE');

        $row->setPolCode($polCode ?: null);
        $row->setPodCode($podCode ?: null);
        $row->setContainerType($containerType);
        $row->setChargeCode($chargeCode ?: null);
        $row->setCurrency($currency);

        if (!$polCode || !$podCode) {
            return $row->setAction('ERROR')->setErrorMessage('Missing POL_CODE or POD_CODE');
        }

        if (!$this->portRepository->findOneBy(['code' => $polCode])) {
            return $row->setAction('ERROR')->setErrorMessage("Unknown POL port code: {$polCode}");
        }

        if (!$this->portRepository->findOneBy(['code' => $podCode])) {
            return $row->setAction('ERROR')->setErrorMessage("Unknown POD port code: {$podCode}");
        }

        $buying  = $buyingRaw !== '' ? (float) $buyingRaw : null;
        $selling = $sellingRaw !== '' ? (float) $sellingRaw : null;

        if ($buying !== null && $buying <= 0) {
            return $row->setAction('ERROR')->setErrorMessage('BUYING_RATE must be positive');
        }
        if ($selling !== null && $selling <= 0) {
            return $row->setAction('ERROR')->setErrorMessage('SELLING_RATE must be positive');
        }

        $row->setNewBuyingAmount($buying !== null ? (string) $buying : null);
        $row->setNewSellingAmount($selling !== null ? (string) $selling : null);

        $existing = $this->rateRepository->findActiveRateForLane(
            $polCode,
            $podCode,
            $job->getProvider()?->getId(),
            $containerType,
            $job->getTransportType()->value
        );

        if ($existing) {
            $currentBuying = $existing->getBuying()?->getAmount();
            $row->setCurrentBuyingAmount($currentBuying !== null ? (string) $currentBuying : null);
            $row->setExistingRateId($existing->getId());
            $row->setPreviousValidUntil($existing->getValidUntil());

            if ($currentBuying && $buying) {
                $changePct = (($buying - $currentBuying) / $currentBuying) * 100;
                $row->setChangePct((string) round($changePct, 4));
                if (abs($changePct) > 50) {
                    $row->setIsSanityFlagged(true);
                }
            }

            $row->setAction('UPDATE');
        } else {
            $row->setAction('NEW');
        }

        return $row;
    }

    public function approve(RateImportJob $job, User $user): void
    {
        if ($job->getStatus() !== 'PREVIEW') {
            throw new \LogicException('Only jobs in PREVIEW status can be approved');
        }

        $em = $this->repository->getEntityManager();
        $rows = $this->rowRepository->findBy(['importJob' => $job]);

        $em->wrapInTransaction(function () use ($job, $user, $em, $rows): void {
            $job->setStatus('IMPORTING');
            $job->setApprovedBy($user);
            $job->setApprovedAt(new \DateTime());

            $imported = 0;
            $skipped  = 0;

            foreach ($rows as $row) {
                if (in_array($row->getAction(), ['ERROR', 'SKIP'], true)) {
                    $skipped++;
                    continue;
                }

                if ($row->getExistingRateId()) {
                    $existing = $this->rateRepository->find($row->getExistingRateId());
                    if ($existing) {
                        $dayBefore = (clone $job->getEffectiveDate())->modify('-1 day');
                        $existing->setValidUntil($dayBefore);
                        $em->persist($existing);
                    }
                }

                $charge = $this->chargeRepository->findOneBy(['customCode' => $row->getChargeCode()]);
                if (!$charge) {
                    $skipped++;
                    continue;
                }

                $polPort = $this->portRepository->findOneBy(['code' => $row->getPolCode()]);
                $podPort = $this->portRepository->findOneBy(['code' => $row->getPodCode()]);

                $rate = new Rate();
                $rate->setCharge($charge);
                $rate->setProvider($job->getProvider());
                $rate->setTransportType($job->getTransportType());
                $rate->setPolPort($polPort);
                $rate->setPodPort($podPort);
                $rate->setValidFrom($job->getEffectiveDate());
                $rate->setValidUntil($job->getExpiryDate());
                $rate->setCreatedBy($user);
                $rate->setImportJob($job);

                if ($row->getContainerType()) {
                    $containerTypeEnum = ContainerType::tryFrom($row->getContainerType());
                    if ($containerTypeEnum === null) {
                        $skipped++;
                        continue;
                    }
                    $rate->setContainerType($containerTypeEnum);
                }

                $rowCurrency = $row->getCurrency() ?? $job->getCurrency();
                if ($row->getNewBuyingAmount() !== null) {
                    $rate->setBuying(new Money((float) $row->getNewBuyingAmount(), $rowCurrency, 1.0));
                }
                if ($row->getNewSellingAmount() !== null) {
                    $rate->setSelling(new Money((float) $row->getNewSellingAmount(), $rowCurrency, 1.0));
                }

                $rate->setChargeType(ChargeType::FREIGHT);

                $em->persist($rate);
                $imported++;
            }

            $job->setStatus('COMPLETED');
            $job->setRowsImported($imported);
            $job->setRowsSkipped($skipped);
            $job->setCompletedAt(new \DateTime());
        });
    }

    public function rollback(RateImportJob $job, User $user): void
    {
        if ($job->getStatus() !== 'COMPLETED') {
            throw new \LogicException('Only COMPLETED jobs can be rolled back');
        }
        if (!$job->isCanRollback()) {
            throw new \LogicException('Rollback is disabled for this import job');
        }

        $completedAt = $job->getCompletedAt();
        if ($completedAt && (new \DateTime())->getTimestamp() - $completedAt->getTimestamp() > 48 * 3600) {
            $job->setCanRollback(false);
            $this->repository->getEntityManager()->flush();
            throw new \LogicException('The 48-hour rollback window has expired');
        }

        $em   = $this->repository->getEntityManager();
        $rows = $this->rowRepository->findBy(['importJob' => $job]);

        $em->wrapInTransaction(function () use ($job, $user, $em, $rows): void {
            foreach ($rows as $row) {
                if ($row->getExistingRateId()) {
                    $existing = $this->rateRepository->find($row->getExistingRateId());
                    if ($existing) {
                        $existing->setValidUntil($row->getPreviousValidUntil());
                        $em->persist($existing);
                    }
                }
            }

            $this->rateRepository->deleteByImportJob($job->getId());

            $job->setStatus('ROLLED_BACK');
            $job->setRolledBackBy($user);
            $job->setRolledBackAt(new \DateTime());
            $job->setCanRollback(false);
        });
    }
}
