<?php
declare(strict_types=1);
namespace App\Module\Compliance\Repository;

use App\Module\Compliance\Entity\SanctionsList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SanctionsListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SanctionsList::class);
    }

    /** @return SanctionsList[] Exact and partial name matches */
    public function findByName(string $name, ?string $listName = null): array
    {
        $qb = $this->createQueryBuilder('sl')
            ->where('sl.isActive = true')
            ->andWhere('sl.listedName LIKE :name')
            ->setParameter('name', '%' . $name . '%')
            ->orderBy('sl.listedName', 'ASC')
            ->setMaxResults(50);

        if ($listName) {
            $qb->andWhere('sl.listName = :ln')->setParameter('ln', $listName);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * PHP-level fuzzy check: returns entries with ≥75% name similarity.
     * For production lists >5000 entries, use a dedicated search service.
     *
     * @return array{entry: SanctionsList, score: float}[]
     */
    public function fuzzyMatch(string $name, float $threshold = 0.75): array
    {
        $allActive = $this->findBy(['isActive' => true]);
        $matches   = [];
        $nameLower = strtolower(trim($name));

        foreach ($allActive as $entry) {
            similar_text($nameLower, strtolower($entry->getListedName()), $pct);
            if ($pct >= $threshold * 100) {
                $matches[] = ['entry' => $entry, 'score' => round($pct, 1)];
                continue;
            }
            foreach ($entry->getAliases() ?? [] as $alias) {
                similar_text($nameLower, strtolower($alias), $aliasPct);
                if ($aliasPct >= $threshold * 100) {
                    $matches[] = ['entry' => $entry, 'score' => round($aliasPct, 1)];
                    break;
                }
            }
        }

        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($matches, 0, 10);
    }

    public function save(SanctionsList $sl): void
    {
        $this->getEntityManager()->persist($sl);
        $this->getEntityManager()->flush();
    }

    public function remove(SanctionsList $sl): void
    {
        $this->getEntityManager()->remove($sl);
        $this->getEntityManager()->flush();
    }
}
