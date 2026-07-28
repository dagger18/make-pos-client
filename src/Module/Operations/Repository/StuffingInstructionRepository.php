<?php

namespace App\Module\Operations\Repository;

use App\Module\Operations\Entity\StuffingInstruction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StuffingInstructionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StuffingInstruction::class);
    }

    public function save(StuffingInstruction $instruction): StuffingInstruction
    {
        $this->getEntityManager()->persist($instruction);
        $this->getEntityManager()->flush();
        return $instruction;
    }

    public function delete(StuffingInstruction $instruction): void
    {
        $this->getEntityManager()->remove($instruction);
        $this->getEntityManager()->flush();
    }

    /** @return StuffingInstruction[] */
    public function findByConsol(int $consolId): array
    {
        return $this->findBy(['consolId' => $consolId], ['createdAt' => 'DESC']);
    }
}
