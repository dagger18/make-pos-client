<?php

namespace App\Module\Operations\Repository;

use App\Module\Operations\Entity\StrippingInstruction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StrippingInstructionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StrippingInstruction::class);
    }

    public function save(StrippingInstruction $instruction): StrippingInstruction
    {
        $this->getEntityManager()->persist($instruction);
        $this->getEntityManager()->flush();
        return $instruction;
    }

    public function delete(StrippingInstruction $instruction): void
    {
        $this->getEntityManager()->remove($instruction);
        $this->getEntityManager()->flush();
    }

    /** @return StrippingInstruction[] */
    public function findByConsol(int $consolId): array
    {
        return $this->findBy(['consolId' => $consolId], ['createdAt' => 'DESC']);
    }
}
