<?php
namespace App\Module\Finance\Repository;

use App\Module\Finance\Entity\ChartOfAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChartOfAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChartOfAccount::class);
    }

    public function findByCode(string $code): ?ChartOfAccount
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function save(ChartOfAccount $account): void
    {
        $em = $this->getEntityManager();
        $em->persist($account);
        $em->flush();
    }
}
