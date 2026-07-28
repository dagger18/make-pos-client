<?php

namespace App\Module\Operations\Repository;

use App\Module\Core\Repository\BaseRepository;

use App\Module\Core\Enum\DateSegment;
use Doctrine\ORM\QueryBuilder;
use App\Module\Core\Enum\TransportType;
use App\Module\Operations\Enum\ShipmentStatus;
use Doctrine\ORM\Query\Expr\Join;
use App\Module\Reporting\Enum\DatasetGroupColumn;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;

class ShipmentRepository extends BaseRepository
{
    public function getNextActiveCode($mysqlVersion, $exceptShipmentId) {
        if($mysqlVersion === 'mysql57') {
            return $this->getCodeForMySql57($exceptShipmentId);
        }
        if($mysqlVersion === 'mysql80') {
            return $this->getCodeForMariaDBAndMysql80('$');
        }
        if($mysqlVersion === 'maria10') {
            return $this->getCodeForMariaDBAndMysql80('\\\\');
        }
        return 0;
    }
    public function getCodeForMySql57($exceptShipmentId) {
        $queryBuilder = $this->createQueryBuilder('Shipment');
        $queryBuilder
            ->where($queryBuilder->expr()->in('Shipment.status', ':statuses'))
            ->andWhere($queryBuilder->expr()->neq('Shipment.id', ':id'))
            ->setParameter(
                'statuses',
                [
                    ShipmentStatus::Active->value,
                    ShipmentStatus::Completed->value,
                    ShipmentStatus::Cancelled->value,
                ],
                ArrayParameterType::STRING
            )
            ->setParameter('id',$exceptShipmentId)
            ->addOrderBy('Shipment.createdDate', 'DESC')
            ->setMaxResults(1)
            ;
        $lastShipment = $queryBuilder->getQuery()->getOneOrNullResult();
        $this->logger->info('lastShipment', [$lastShipment]);
        if(is_null($lastShipment)) {
            $lastNum = 0;
        } else {
            preg_match('/[A-Z]+_([0-9]+)_/m', $lastShipment->getCode(), $matches);
            $lastNum = (int) $matches[1];
        }
        $this->logger->info('lastNum', [$lastNum]);
        return str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
    }
    public function getCodeForMariaDBAndMysql80($paramSymbol = '$') {
        $conn = $this->getConnection();
        $lastShipmentNumber = $conn->fetchOne("
            SELECT MAX(CAST(REGEXP_REPLACE(code,'(.*)_(.*)_(.*)_(.*)','" . $paramSymbol . "2') as UNSIGNED))
            from shipment
            group by null; "
        );
        $this->logger->info('lastShipment', [$lastShipmentNumber]);
        if(!$lastShipmentNumber) {
            $lastShipmentNumber = 0;
        }
        return str_pad(1 + (int) $lastShipmentNumber, 4, '0', STR_PAD_LEFT);
    }

    public function getGroupData(QueryBuilder $queryBuilder, $requestData) {
        $groupColumn =  DatasetGroupColumn::from($requestData['groupColumn']);

        $queryBuilder->resetDQLPart('select');
        if(!in_array('Quote', $queryBuilder->getAllAliases()))
            $queryBuilder->leftJoin('Shipment.quote', 'Quote');
        if(!in_array('Instruction', $queryBuilder->getAllAliases()))
            $queryBuilder->leftJoin('Shipment.instruction', 'Instruction');
        if(!in_array('Booking', $queryBuilder->getAllAliases()))
            $queryBuilder->leftJoin('Shipment.booking', 'Booking');
        
        $totalQueryBuilder = clone $queryBuilder;
        switch ($groupColumn) {
            case DatasetGroupColumn::CompletedDate:
                $dateSegment =  DateSegment::from($requestData['dateSegment']);
                $queryBuilder->addSelect(
                    $dateSegment->computeQuery('Shipment', $groupColumn)
                );
                $queryBuilder->addGroupBy('acompletedDate');
                break;
            case DatasetGroupColumn::ETD:
                    $dateSegment =  DateSegment::from($requestData['dateSegment']);
                    $queryBuilder->addSelect(
                        $dateSegment->computeQuery('Booking', $groupColumn)
                    );
                    $queryBuilder->addGroupBy('aetd');
                    break;
            case DatasetGroupColumn::CreatedBy:
                if(!in_array('CreatedBy', $queryBuilder->getAllAliases()))
                    $queryBuilder->leftJoin('Shipment.createdBy', 'CreatedBy');
                $queryBuilder->addSelect("CONCAT(CreatedBy.firstName,' ',CreatedBy.lastName)");
                $queryBuilder->addGroupBy('Shipment.createdBy');
                break;
            case DatasetGroupColumn::AccountManager:
                if(!in_array('AccountManager', $queryBuilder->getAllAliases()))
                    $queryBuilder->leftJoin('Shipment.accountManager', 'AccountManager');
                $queryBuilder->addSelect("CONCAT(AccountManager.firstName,' ',AccountManager.lastName)");
                $queryBuilder->addGroupBy('Shipment.accountManager');
                break;
            case DatasetGroupColumn::Client:
                if(!in_array('Client', $queryBuilder->getAllAliases()))
                    $queryBuilder->leftJoin('Quote.client', 'Client');
                $queryBuilder->addSelect("Client.name");
                $queryBuilder->addGroupBy('Client');
                break;
            case DatasetGroupColumn::Provider:
                if(!in_array('Provider', $queryBuilder->getAllAliases()))
                    $queryBuilder->leftJoin('Quote.provider', 'Provider');
                $queryBuilder->addSelect("Provider.name");
                $queryBuilder->addGroupBy('Provider');
                break;
            case DatasetGroupColumn::Origin:
                $queryBuilder->addSelect("Quote.originDoor");
                $queryBuilder->addGroupBy('Quote.originDoor');
                break;
            case DatasetGroupColumn::Destination:
                $queryBuilder->addSelect("Quote.destinationDoor");
                $queryBuilder->addGroupBy('Quote.destinationDoor');
                break;
            case DatasetGroupColumn::Route:
                $queryBuilder->addSelect("CONCAT(Quote.originDoor, '\n→' , Quote.destinationDoor) as Route");
                $queryBuilder->addGroupBy("Route");
                break;
        }
        $volumeIndex = null;
        forEach($requestData['columns'] as $index => $column) {
            $select = "'what the'";
            switch ($column) {
                case 'shipment':
                    $select = 'COUNT(DISTINCT Shipment.id)';
                    break;
                case 'provider':
                    $select = 'COUNT(DISTINCT Quote.provider)';
                    break;
                case 'client':
                    $select = 'COUNT(DISTINCT Quote.client)';
                    break;
                case 'origin':
                    $select = 'COUNT(DISTINCT Quote.originDoor)';
                    break;
                case 'destination':
                    $select = 'COUNT(DISTINCT Quote.destinationDoor)';
                    break;
                case 'volume':
                    $volumeIndex = $index;
                    $select = "
                        CASE 
                            WHEN Quote.transportType = 'AIR'
                                THEN CONCAT(
                                    FORMAT(ROUND(SUM(JSON_EXTRACT(Quote.cargoVolume, '$.chargeableWeight'))),0),
                                        ''
                                    )
                            WHEN Quote.transportType = 'LCL'
                                THEN CONCAT(
                                        ROUND(SUM(JSON_EXTRACT(Quote.cargoVolume, '$.totalCBM'))),
                                        ''
                                    )
                            WHEN Quote.transportType = 'FCL'
                                THEN ''
                            ELSE
                                'uncountable'
                        END
                    ";
                    break;
                case 'chargeableWeight':
                        $select = "
                            CASE 
                                WHEN Quote.transportType = 'AIR'
                                    THEN FORMAT(ROUND(SUM(JSON_EXTRACT(Quote.cargoVolume, '$.chargeableWeight'))),0)
                                ELSE
                                    '__'
                            END
                        ";
                        break;
                case 'grossWeight':
                    $select = "
                        CASE 
                            WHEN Quote.transportType = 'AIR'
                                THEN FORMAT(ROUND(SUM(JSON_EXTRACT(Quote.cargoVolume, '$.totalWeight'))),0)
                            ELSE
                                '__'
                        END
                    ";
                    break;
                case 'totalUnit':
                    $select = "
                            CASE 
                                WHEN Quote.transportType = 'AIR'
                                    THEN SUM(JSON_EXTRACT(Booking.cargoVolume, '$.totalUnit'))
                                WHEN Quote.transportType = 'LCL'
                                    THEN SUM(JSON_EXTRACT(Booking.cargoVolume, '$.totalUnit'))
                                ELSE
                                    '__'
                            END
                        ";
                    break;
                case 'revenue':
                    $select = 'FORMAT(ROUND(SUM(Shipment.revenue.amount)),0)';
                    break;
                case 'cost':
                    $select = 'FORMAT(ROUND(SUM(Shipment.cost.amount)),0)';
                    break;
                case 'profit':
                    $select = 'FORMAT(ROUND(SUM(Shipment.profit.amount)),0)';
                    break;
                case 'commissionProvider':
                    $select = 'FORMAT(ROUND(SUM(Shipment.commissionProvider.amount)),0)';
                    break;
                case 'commissionClient':
                    $select = 'FORMAT(ROUND(SUM(Shipment.commissionClient.amount)),0)';
                    break;
                case 'buying':
                    $select = "FORMAT(ROUND(SUM(Quote.totalBuying * JSON_EXTRACT(Quote.exchangeRates, '$.VND'))),0)";
                    break;
                case 'selling':
                    $select = "FORMAT(ROUND(SUM(Quote.totalSelling * JSON_EXTRACT(Quote.exchangeRates, '$.VND'))),0)";
                    break;
            }
            $queryBuilder->addSelect($select . ' as b' . $index); 
            $totalQueryBuilder->addSelect($select . ' as b' . $index); 
        }
        $result = $queryBuilder->getQuery()->execute();
        $totalResult = $totalQueryBuilder->getQuery()->execute();
        $totalResult = ['Total', ...array_values($totalResult[0])];
        $result = array_map('array_values', $result);
        if(is_null($volumeIndex)) {
            return [
                'data' => $result,
                'total' => $totalResult
            ];
        }
        forEach($result as &$row) {
            if(!is_null($volumeIndex) 
                && str_starts_with($row[$volumeIndex], '[') 
                && str_ends_with($row[$volumeIndex], ']')
            ) {
                $containerMap = [];
                $cluster = json_decode($row[$volumeIndex], true);
                forEach($cluster as $containers) {
                    if(is_null($containers)) continue;
                    forEach($containers as $container) {
                        if(!isset($containerMap[$container])) {
                            $containerMap[$container] = 0;
                        }
                        $containerMap[$container] += 1;
                    }
                }
                $containerStrings = [];
                forEach($containerMap as $type => $count){
                    $containerStrings[] = $count . ' x ' . $type;
                }
                $row[$volumeIndex] = implode(', ', $containerStrings);
            }
        }
        return [
            'data' => $result,
            'total' => $totalResult
        ];
    }
    public function getGroupColumnEntityData(QueryBuilder $queryBuilder, $requestData) {
        $groupColumn =  DatasetGroupColumn::from($requestData['groupColumn']);
        $staffFilter = DatasetGroupColumn::from($requestData['staffProperty']);
        $queryBuilder->resetDQLPart('select');
        $totalQueryBuilder = clone $queryBuilder;
        if(!in_array('Quote', $queryBuilder->getAllAliases()))
            $queryBuilder->leftJoin('Shipment.quote', 'Quote');
        if(!in_array('Instruction', $queryBuilder->getAllAliases()))
            $queryBuilder->leftJoin('Shipment.instruction', 'Instruction');
        if(!in_array('Booking', $queryBuilder->getAllAliases()))
            $queryBuilder->leftJoin('Shipment.booking', 'Booking');
        
        switch ($groupColumn) {
            case DatasetGroupColumn::ShipmentCompletedDate:
                $dateSegment =  DateSegment::from($requestData['dateSegment']);
                $queryBuilder->addSelect(
                    $dateSegment->computeQuery('Shipment', DatasetGroupColumn::CompletedDate)
                );
                $queryBuilder->addGroupBy('acompletedDate');
                break;
        }
        $select = null;
        switch ($requestData['groupColumnEntity']) {
            case 'shipment':
                $select = 'COUNT(DISTINCT 
                                CASE 
                                    WHEN 
                                        %condition%
                                        THEN Shipment.id
                                    ELSE NULLIF(1,1)
                                END
                            )';
                break;
            case 'provider':
                $select = 'COUNT(DISTINCT 
                                CASE 
                                    WHEN 
                                        %condition%
                                        THEN Quote.provider
                                    ELSE NULLIF(1,1)
                                END
                            )';
                break;
            case 'client':
                $select = 'COUNT(DISTINCT 
                                CASE 
                                    WHEN 
                                        %condition%
                                        THEN Quote.client
                                    ELSE NULLIF(1,1)
                                END
                            )';
                break;
            case 'origin':
                $select = 'COUNT(DISTINCT 
                                CASE 
                                    WHEN 
                                        %condition%
                                        THEN Quote.originDoor
                                    ELSE NULLIF(1,1)
                                END
                            )';
                break;
            case 'destination':
                $select = 'COUNT(DISTINCT 
                                CASE 
                                    WHEN 
                                        %condition%
                                        THEN Quote.destinationDoor
                                    ELSE NULLIF(1,1)
                                END
                            )';
                break;
            case 'volume':
                $select = "
                    CASE 
                        WHEN Quote.transportType = 'AIR'
                            AND %condition%
                            THEN CONCAT(
                                FORMAT(ROUND(SUM(JSON_EXTRACT(Quote.cargoVolume, '$.chargeableWeight'))),0),
                                    ''
                                )
                        WHEN Quote.transportType = 'LCL'
                            AND %condition%
                            THEN CONCAT(
                                    ROUND(SUM(JSON_EXTRACT(Quote.cargoVolume, '$.totalCBM'))),
                                    ''
                                )
                        WHEN Quote.transportType = 'FCL'
                            AND %condition%
                            THEN 0
                        ELSE
                            0
                    END
                ";
                break;
            case 'revenue':
                $select = 'FORMAT(ROUND(
                                SUM(
                                    CASE 
                                        WHEN 
                                            %condition%
                                            THEN Shipment.revenue.amount
                                        ELSE 0
                                    END
                                )
                            ),0)';
                break;
            case 'cost':
                $select = 'FORMAT(ROUND(
                                SUM(
                                    CASE 
                                        WHEN 
                                            %condition%
                                            THEN Shipment.cost.amount
                                        ELSE 0
                                    END
                                )
                            ),0)';
                break;
            case 'profit':
                $select = 'FORMAT(ROUND(
                                SUM(
                                    CASE 
                                        WHEN 
                                            %condition%
                                            THEN Shipment.profit.amount
                                        ELSE 0
                                    END
                                )
                            ),0)';
                break;
            
        }
        forEach($requestData['columns'] as $index => $userId) {
            $condition = "Shipment." . $staffFilter->value . " = " . $userId ;
            $queryBuilder->addSelect(str_replace('%condition%', $condition, $select) . ' as b' . $index); 
            $totalQueryBuilder->addSelect(str_replace('%condition%', $condition, $select) . ' as b' . $index); 
        }
        $this->logger->info('final query builder', [$queryBuilder->getDQL()]);
        $this->logger->info('final query builder query', [$queryBuilder->getQuery()->getSQL()]);
        $result = $queryBuilder->getQuery()->execute();
        $totalResult = $totalQueryBuilder->getQuery()->execute();
        $totalResult = ['Total', ...array_values($totalResult[0])];
        return [
            'data' => array_map('array_values', $result),
            'total' => $totalResult
        ];
    }
    public function getEmptyCommissionList() {
        $queryBuilder = $this->createQueryBuilder('Shipment');
        $queryBuilder
            ->where(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull('Shipment.commissionProvider.amount'),
                    $queryBuilder->expr()->isNull('Shipment.commissionClient.amount'),
                )
            )
            ;
        return $queryBuilder->getQuery()->execute();
    }

    public function getShipmentFromBooking(int $bookingId): ?int
    {
        $result = $this->createQueryBuilder('Shipment')
            ->select('Shipment.id')
            ->join('Shipment.booking', 'Booking')
            ->where('Booking.id = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['id'] : null;
    }

    public function findActiveByClient(int $clientId): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.quote', 'q')
            ->join('q.client', 'c')
            ->where('c.id = :clientId')
            ->setParameter('clientId', $clientId)
            ->getQuery()
            ->getResult();
    }
}
