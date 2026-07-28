<?php
namespace App\Module\Insurance\Repository;

use App\Module\Insurance\Entity\InsuranceDeclaration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InsuranceDeclarationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InsuranceDeclaration::class);
    }

    public function generateDeclarationRef(int $policyId): string
    {
        $ym = date('Ym');
        $conn = $this->getEntityManager()->getConnection();
        $count = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM insurance_declaration WHERE policy_id = ? AND SUBSTR(created_at, 1, 6) = ?",
            [$policyId, $ym]
        );
        return sprintf('DCL-%d-%s-%02d', $policyId, $ym, $count + 1);
    }

    public function save(InsuranceDeclaration $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(InsuranceDeclaration $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
