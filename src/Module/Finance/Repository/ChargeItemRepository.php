<?php

namespace App\Module\Finance\Repository;

use App\Module\Core\Entity\User;
use App\Module\Core\Repository\BaseRepository;

class ChargeItemRepository extends BaseRepository
{
    /**
     * Returns raw charge-item rows for a shipment filtered by the current user's department visibleTo mapping.
     *
     * Mapping (department direction → visible_to values shown to user):
     *   EXP → ORIGIN   (plus NULL and ALL)
     *   IMP → DESTINATION (plus NULL and ALL)
     *   null / XTD / DOM / TSH → no filter (show all)
     *
     * ROLE_MANAGER and ROLE_ADMIN bypass the filter entirely.
     *
     * @return list<array<string, mixed>>
     */
    public function findByShipmentFiltered(int $shipmentId, User $user): array
    {
        $roles        = $user->getRoles();
        $isPrivileged = (bool) array_intersect(['ROLE_MANAGER', 'ROLE_ADMIN'], $roles);

        $params       = ['shipmentId' => $shipmentId];
        $filterClause = '';

        if (!$isPrivileged) {
            $allowed = [];
            $showAll = false;

            foreach ($user->getDepartments() as $dept) {
                $dir = $dept->getDirection();
                if ($dir === null) {
                    $showAll = true;
                    break;
                }
                $mapped = match ($dir->value) {
                    'EXP' => 'ORIGIN',
                    'IMP' => 'DESTINATION',
                    default => null,
                };
                if ($mapped === null) {
                    $showAll = true;
                    break;
                }
                $allowed[] = $mapped;
            }

            if (!$showAll) {
                if (empty($allowed)) {
                    $filterClause = "AND (ci.visible_to IS NULL OR ci.visible_to = 'ALL')";
                } else {
                    $parts = [];
                    foreach (array_values(array_unique($allowed)) as $i => $val) {
                        $key          = "dir_{$i}";
                        $params[$key] = $val;
                        $parts[]      = ":{$key}";
                    }
                    $inList       = implode(', ', $parts);
                    $filterClause = "AND (ci.visible_to IS NULL OR ci.visible_to = 'ALL' OR ci.visible_to IN ({$inList}))";
                }
            }
        }

        $sql = "
            SELECT ci.*
            FROM charge_item ci
            JOIN ebit_note en ON ci.ebit_note_id = en.id
            WHERE en.shipment_id = :shipmentId
              AND en.type IN ('ID', 'IC')
              {$filterClause}
            ORDER BY ci.id
        ";

        return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, $params);
    }
}
