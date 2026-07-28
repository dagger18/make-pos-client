<?php

namespace App\Module\Operations\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Operations\Entity\ShipmentActivity;
use App\Module\Core\Enum\DateSegment;
use Doctrine\ORM\QueryBuilder;
use App\Module\Reporting\Enum\DatasetGroupColumn;
use App\Module\Core\Enum\EntityType;
use App\Module\Operations\Enum\ShipmentActivityType;

class ShipmentActivityRepository extends BaseRepository
{
    public function findOrCreate(int $shipmentId): ShipmentActivity
    {
        $record = $this->find($shipmentId);
        if (!$record) {
            $record = new ShipmentActivity($shipmentId);
            $this->getEntityManager()->persist($record);
        }
        return $record;
    }

    public function getGroupData(QueryBuilder $queryBuilder, $requestData) {
        $groupColumn =  DatasetGroupColumn::from($requestData['groupColumn']);

        $queryBuilder->resetDQLPart('select');
        $totalQueryBuilder = clone $queryBuilder;
        switch ($groupColumn) {
            case DatasetGroupColumn::CreatedDate:
                $dateSegment =  DateSegment::from($requestData['dateSegment']);
                $queryBuilder->addSelect(
                    $dateSegment->computeQuery('ShipmentActivity', $groupColumn)
                );
                $queryBuilder->addGroupBy('acreatedDate');
                break;
        }
        
        forEach($requestData['columns'] as $index => $column) {
            $select = "'what the'";
            switch ($column) {
                case 'activities':
                    $select = 'COUNT(DISTINCT ShipmentActivity.id)';
                    break;
                case 'shipmentCreated':
                    $select = "
                    SUM(
                        CASE 
                            WHEN ShipmentActivity.type = '" . ShipmentActivityType::Create->value . "' THEN 1
                            WHEN ShipmentActivity.type = '" . ShipmentActivityType::Replicate->value . "' THEN 1
                            ELSE 0
                        END
                    )";
                    break;
                case 'statusUpdate':
                    $select = "
                    SUM(
                        CASE 
                            WHEN ShipmentActivity.type = '" . ShipmentActivityType::UpdateStatus->value . "' THEN 1
                            ELSE 0
                        END
                    )";
                    break;
                case 'pricingUpdate':
                    $select = "
                    SUM(
                        CASE 
                            WHEN 
                                ShipmentActivity.type = '" . ShipmentActivityType::SubUpdate->value . "' 
                                AND ShipmentActivity.subEntityType = '" . EntityType::Quote->value .  "'
                                THEN 1
                            ELSE 0
                        END
                    )";
                    break;
                case 'instructionUpdate':
                    $select = "
                    SUM(
                        CASE 
                            WHEN 
                                ShipmentActivity.type = '" . ShipmentActivityType::SubUpdate->value . "' 
                                AND ShipmentActivity.subEntityType = '" . EntityType::Instruction->value .  "'
                                THEN 1
                            ELSE 0
                        END
                    )";
                    break;
                case 'bookingUpdate':
                    $select = "
                    SUM(
                        CASE 
                            WHEN 
                                ShipmentActivity.type = '" . ShipmentActivityType::SubUpdate->value . "' 
                                AND ShipmentActivity.subEntityType = '" . EntityType::Booking->value .  "'
                                THEN 1
                            ELSE 0
                        END
                    )";
                    break;
                case 'documentCreate':
                    $select = "
                    SUM(
                        CASE 
                            WHEN 
                                ShipmentActivity.type = '" . ShipmentActivityType::SubCreate->value . "' 
                                AND ShipmentActivity.subEntityType = '" . EntityType::Document->value .  "'
                                THEN 1
                            ELSE 0
                        END
                    )";
                    break;
                case 'ebitnoteCreate':
                    $select = "
                    SUM(
                        CASE 
                            WHEN 
                                ShipmentActivity.type = '" . ShipmentActivityType::SubCreate->value . "' 
                                AND ShipmentActivity.subEntityType = '" . EntityType::EbitNote->value .  "'
                                THEN 1
                            ELSE 0
                        END
                    )";
                    break;
            }
            $queryBuilder->addSelect($select . ' as b' . $index); 
            $totalQueryBuilder->addSelect($select . ' as b' . $index); 
        }
        $result = $queryBuilder->getQuery()->execute();
        $totalResult = $totalQueryBuilder->getQuery()->execute();
        return [
            'data' => array_map('array_values', $result),
            'total' => ['Total', ...array_values($totalResult[0])]
        ];
    }
    public function getGroupColumnEntityData(QueryBuilder $queryBuilder, $requestData) {
        $groupColumn =  DatasetGroupColumn::from($requestData['groupColumn']);

        $queryBuilder->resetDQLPart('select');
        
        switch ($requestData['groupColumnEntity']) {
            case 'activities':
                break;
            case 'shipmentCreated':
                $queryBuilder
                    ->andWhere("
                        ShipmentActivity.type = '" . ShipmentActivityType::Create->value . "' 
                        OR ShipmentActivity.type = '" . ShipmentActivityType::Replicate->value . "'
                    ")
                    ;
                break;
            case 'statusUpdate':
                $queryBuilder
                    ->andWhere("ShipmentActivity.type = '" . ShipmentActivityType::UpdateStatus->value . "'");
                break;
            case 'pricingUpdate':
                $queryBuilder
                    ->andWhere("ShipmentActivity.type = '" . ShipmentActivityType::SubUpdate->value . "'")
                    ->andWhere("ShipmentActivity.subEntityType = '" . EntityType::Quote->value . "'")
                    ;
                break;
            case 'instructionUpdate':
                $queryBuilder
                    ->andWhere("ShipmentActivity.type = '" . ShipmentActivityType::SubUpdate->value . "'")
                    ->andWhere("ShipmentActivity.subEntityType = '" . EntityType::Instruction->value . "'")
                    ;
                break;
            case 'bookingUpdate':
                $queryBuilder
                    ->andWhere("ShipmentActivity.type = '" . ShipmentActivityType::SubUpdate->value . "'")
                    ->andWhere("ShipmentActivity.subEntityType = '" . EntityType::Booking->value . "'")
                    ;
                break;
            case 'documentCreate':
                $queryBuilder
                    ->andWhere("ShipmentActivity.type = '" . ShipmentActivityType::SubCreate->value . "'")
                    ->andWhere("ShipmentActivity.subEntityType = '" . EntityType::Document->value . "'")
                    ;
                break;
            case 'ebitnoteCreate':
                $queryBuilder
                    ->andWhere("ShipmentActivity.type = '" . ShipmentActivityType::SubCreate->value . "'")
                    ->andWhere("ShipmentActivity.subEntityType = '" . EntityType::EbitNote->value . "'")
                    ;
                break;
        }
        // todo: check for missing create shipment activity
        /*
        select s.id, sa.id
            from shipment s
            left join shipment_activity sa
                on s.id = sa.shipment_id and (sa.type = 'CE' or sa.type = 'RE')
            where s.status = 'CO' and sa.id is null
        */
        $totalQueryBuilder = clone $queryBuilder;
        switch ($groupColumn) {
            case DatasetGroupColumn::ActivityCreatedDate:
                $dateSegment =  DateSegment::from($requestData['dateSegment']);
                $queryBuilder->addSelect(
                    $dateSegment->computeQuery('ShipmentActivity', DatasetGroupColumn::CreatedDate)
                );
                $queryBuilder->addGroupBy('acreatedDate');
                break;
        }
        forEach($requestData['columns'] as $index => $userId) {
            $select = "
                    SUM(
                        CASE 
                            WHEN 
                                ShipmentActivity.userId = " . $userId . "
                                THEN 1
                            ELSE 0
                        END
                    )";
            $queryBuilder->addSelect($select . ' as b' . $index); 
            $totalQueryBuilder->addSelect($select . ' as b' . $index); 
        }
        $result = $queryBuilder->getQuery()->execute();
        $totalResult = $totalQueryBuilder->getQuery()->execute();
        return [
            'data' => array_map('array_values', $result),
            'total' => ['Total', ...array_values($totalResult[0])]
        ];
    }
}
