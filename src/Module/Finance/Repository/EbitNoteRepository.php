<?php

namespace App\Module\Finance\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Crm\Entity\Client;
use App\Module\Finance\Entity\EbitNote;
use App\Module\Finance\Enum\EbitNoteStatus;
use App\Module\Finance\Enum\EbitNoteType;

class EbitNoteRepository extends BaseRepository
{
    public function getNextActiveCode(EbitNoteType $type, int $codeIndex = null) {
        if(
            $type === EbitNoteType::InvoiceCredit
            || $type === EbitNoteType::InvoiceDebit
            || $type === EbitNoteType::POBO
            || $type === EbitNoteType::COBO
        ) {
            return str_pad($this->getMaxCode($type,$codeIndex), 4, '0', STR_PAD_LEFT) ;
        } else {
            return str_pad($this->getMaxCode($type,$codeIndex), 3, '0', STR_PAD_LEFT) ;
        }
    }

    public function getMaxCode(EbitNoteType $type, int $codeIndex = null) {
        $from = (new \DateTime('first day of this month'))->format('Y-m-01 00:00:00');
        $to = (new \DateTime('last day of this month'))->format('Y-m-d 23:59:59');

        $queryBuilder = $this->createQueryBuilder('EbitNote');
        $queryBuilder
            ->andWhere('EbitNote.type = :type')
            ->setParameter('type', $type->value)
            ->addOrderBy('EbitNote.id', 'DESC')
            ->andWhere('EbitNote.createdDate BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setMaxResults(1)
            ;
        $last = $queryBuilder->getQuery()->getOneOrNullResult();
        $this->logger->info('last ebitnote for global '  . $type->value, [$last]);
        if(is_null($last)) {
            $lastNum = 1;
        } else {
            preg_match('/[A-Z]{2}_?[0-9]{4}_?([0-9]+)/m', $last->getCode(), $matches);
            if(!isset($matches[1])) {
                $lastNum = 1;
            } else {
                $lastNum = (int) $matches[1];
            }
        }
        $lastNum += $codeIndex + 1;
        $this->logger->info('lastNum', [$lastNum]);
        return $lastNum;
    }

    public function getMaxCodeThisMonth(EbitNoteType $type, int $codeIndex = null) {
        $from = (new \DateTime('first day of this month'))->format('Y-m-01 00:00:00');
        $to = (new \DateTime('last day of this month'))->format('Y-m-d 23:59:59');

        $queryBuilder = $this->createQueryBuilder('q');
        $queryBuilder
            ->select('count(q.id)')
            ->andWhere('q.createdDate BETWEEN :from AND :to')
            ->andWhere('q.type = :type')
            ->setParameter('type', $type->value)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
        ;
        $lastNum = $queryBuilder->getQuery()->getSingleScalarResult() + 1;
        if(!is_null($codeIndex)) {
            $lastNum += $codeIndex;
        }
        
        $this->logger->info('last ebitnote for ThisMonth '  . $type->value, [$lastNum]);
        return str_pad($lastNum, 3, '0', STR_PAD_LEFT);
    }

    public function getAllInvoices(EbitNote $ebitNote) {
        $qb = $this->createQueryBuilder('q');
        return $qb
            ->andWhere('q.parentNote = :parentNote')

            ->setParameter('parentNote', $ebitNote->getId())
            ->getQuery()->execute()
        ;
    }

    public function getPaidInvoice(EbitNote $invoice) {
        $conn = $this->getConnection();
        $result = $conn->fetchNumeric("
            select sum(amount_amount), max(created_date)
            from ebit_note 
            where parent_note_id = " . $invoice->getId() . "
            group by null "
        );
        return !$result ? [0, ''] : $result;
    }

    public function getByMonth(EbitNoteType $type, $month) {
        $from = $month . '-01 00:00:00';
        $to = date($month . '-t 23:59:59');
        $queryBuilder = $this->createQueryBuilder('EbitNote');
        $queryBuilder
            ->andWhere('EbitNote.createdDate BETWEEN :from AND :to')
            ->andWhere('EbitNote.type = :type')
            ->setParameter('type', $type->value)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
        ;
        return $queryBuilder->getQuery()->execute();
    }

    public function getClientUnpaidTotalUSD(Client $client) {
        return $this->getConnection()->fetchOne("
            select sum(amount_amount/amount_rate)
            from ebit_note
            where type = '" . EbitNoteType::Debit->value . "' 
                and status != '" . EbitNoteStatus::Done->value . "'
                and collect_from_id = {$client->getId()}
                and created_date > '2025-01-01 00:00:00'
            group by null
        "
        );
    }

    public function deletePaymentRecord($invoiceDebitId) {
        $this->getConnection()->delete('ebit_note', [
            'parent_note_id' => $invoiceDebitId,
            'type' => EbitNoteType::RecordReceipt->value
        ]);
    }
}