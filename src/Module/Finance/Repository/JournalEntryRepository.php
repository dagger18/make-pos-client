<?php
namespace App\Module\Finance\Repository;

use App\Module\Finance\Entity\JournalEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class JournalEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalEntry::class);
    }

    public function findByEbitNote(int $ebitNoteId): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.ebitNote = :id')
            ->setParameter('id', $ebitNoteId)
            ->orderBy('j.entryDate', 'DESC')
            ->getQuery()->getResult();
    }

    public function save(JournalEntry $entry): void
    {
        $em = $this->getEntityManager();
        $em->persist($entry);
        $em->flush();
    }
}
