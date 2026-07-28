<?php
declare(strict_types=1);
namespace App\Module\Lc\Service;

use App\Module\Lc\Entity\LcDiscrepancy;
use App\Module\Lc\Entity\LetterOfCredit;
use Doctrine\ORM\EntityManagerInterface;

class LcComplianceService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * Run spec-defined compliance checks against the LC and provided document values.
     *
     * @param array $params {
     *   blOnBoardDate?: string (Y-m-d)   — actual on-board date from the issued BL
     *   invoiceAmount?: float             — total amount on the commercial invoice
     *   insuranceAmount?: float           — insured amount on the insurance certificate
     *   portNamesConsistent?: bool        — manual confirmation that port names match across all docs
     * }
     * @return array list of check result arrays
     */
    public function runChecks(LetterOfCredit $lc, array $params): array
    {
        $checks = [];
        $today  = new \DateTime();

        if (isset($params['blOnBoardDate'])) {
            $blDate = new \DateTime($params['blOnBoardDate']);
            $passed = $blDate <= $lc->getShipmentBy();
            $checks[] = [
                'code'     => 'bl_on_board_date',
                'severity' => 'FATAL',
                'passed'   => $passed,
                'message'  => $passed
                    ? "BL on-board date {$params['blOnBoardDate']} is on or before shipment-by {$lc->getShipmentBy()->format('Y-m-d')}"
                    : "BL on-board date {$params['blOnBoardDate']} is AFTER shipment-by {$lc->getShipmentBy()->format('Y-m-d')} — payment will be refused",
            ];
        }

        if (isset($params['invoiceAmount'])) {
            $passed = (float)$params['invoiceAmount'] <= (float)$lc->getLcAmount();
            $checks[] = [
                'code'     => 'invoice_amount_matches',
                'severity' => 'FATAL',
                'passed'   => $passed,
                'message'  => $passed
                    ? "Invoice amount {$params['invoiceAmount']} does not exceed LC amount {$lc->getLcAmount()} {$lc->getLcCurrency()}"
                    : "Invoice amount {$params['invoiceAmount']} exceeds LC amount {$lc->getLcAmount()} {$lc->getLcCurrency()}",
            ];
        }

        if ($lc->getPresentationDeadline()) {
            $deadline = $lc->getPresentationDeadline();
            $passed   = $today <= $deadline;
            $checks[] = [
                'code'     => 'presentation_deadline',
                'severity' => 'FATAL',
                'passed'   => $passed,
                'message'  => $passed
                    ? "Today {$today->format('Y-m-d')} is within the presentation deadline {$deadline->format('Y-m-d')}"
                    : "TODAY IS PAST THE PRESENTATION DEADLINE {$deadline->format('Y-m-d')}",
            ];
        }

        if (isset($params['invoiceAmount'], $params['insuranceAmount'])) {
            $minRequired = (float)$params['invoiceAmount'] * 1.10;
            $passed      = (float)$params['insuranceAmount'] >= $minRequired;
            $checks[] = [
                'code'     => 'insurance_coverage',
                'severity' => 'FATAL',
                'passed'   => $passed,
                'message'  => $passed
                    ? "Insurance amount {$params['insuranceAmount']} meets 110% CIF requirement ({$minRequired})"
                    : "Insurance amount {$params['insuranceAmount']} is below 110% CIF requirement (minimum {$minRequired})",
            ];
        }

        if (isset($params['portNamesConsistent'])) {
            $passed   = (bool)$params['portNamesConsistent'];
            $checks[] = [
                'code'     => 'port_name_consistency',
                'severity' => 'WARNING',
                'passed'   => $passed,
                'message'  => $passed
                    ? 'Port names are consistent across all documents'
                    : 'Port name discrepancy detected — verify spelling across BL, invoice, and packing list',
            ];
        }

        return $checks;
    }

    /** Persist a LcDiscrepancy record for every failed check. Returns count created. */
    public function persistFailedChecks(LetterOfCredit $lc, array $checks): int
    {
        $count = 0;
        foreach ($checks as $check) {
            if (!$check['passed']) {
                $disc = new LcDiscrepancy();
                $disc->setLc($lc)
                    ->setCheckCode($check['code'])
                    ->setSeverity($check['severity'])
                    ->setDescription($check['message']);
                $this->em->persist($disc);
                $count++;
            }
        }
        if ($count > 0) {
            $this->em->flush();
        }
        return $count;
    }

    public function overallStatus(array $checks): string
    {
        foreach ($checks as $check) {
            if (!$check['passed'] && $check['severity'] === 'FATAL') {
                return 'FAIL';
            }
        }
        foreach ($checks as $check) {
            if (!$check['passed']) {
                return 'WARNING';
            }
        }
        return 'PASS';
    }
}
