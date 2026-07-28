<?php

namespace App\Module\Core\Repository;

use App\Module\Core\Entity\SubEntity;
use App\Module\Core\Enum\DateRange;
use Psr\Log\LoggerInterface;
use App\Module\Core\Service\CommonService;
use Doctrine\ORM\QueryBuilder;
use App\Module\Reporting\Enum\DatasetRowType;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\ServiceCollectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class BaseRepository extends ServiceEntityRepository
{
    /**
     * @param TraceableAdapter $appCache
     * @param Serializer $serializer
    */
    public function __construct(
        ManagerRegistry $registry,
        protected CommonService $commonService,
        protected ContainerInterface $container,
        protected CacheInterface $appCache,
        protected ServiceCollectionInterface  $serviceLocator,
        protected ParameterBagInterface $params,
        protected SerializerInterface $serializer,
        protected LoggerInterface $logger,
    )
    {
        $repoClass = get_class($this);
        $shortName = $this->commonService->getClassName($this, true);
        if ($shortName !== 'Base') {
            // Derive entity class from repository namespace:
            // App\Module\Foo\Repository\BarRepository → App\Module\Foo\Entity\Bar
            $entityClass = str_replace('\\Repository\\', '\\Entity\\', $repoClass);
            $entityClass = preg_replace('/Repository$/', '', $entityClass);
            parent::__construct($registry, $entityClass);
        }
    }

    public function saveSubEntity(
        SubEntity $entity, 
        Request $request
    ) {
        $parentRepo = $this
            ->serviceLocator
            ->get('App\Service\\' . ucfirst($request->get('parentType')) . 'Service')->repository;
        $parent = $parentRepo->find($request->get('parentId'));
        $addMethod = 'add' . ucfirst($request->get('parentProperty'));
        $setMethod = 'set' . ucfirst($request->get('parentProperty'));
        if(method_exists($parent, $addMethod)) {
            $parent->$addMethod($entity);
        } else {
            $parent->$setMethod($entity);
        }
        $parentRepo->save($parent);
        return $entity;
    }

    public function save($entity, ?Request $request = null)
    {
        if($entity instanceof SubEntity && $request && $request->get('parentType') && $request->get('parentId') && $request->get('parentProperty')) {
            return $this->saveSubEntity($entity, $request);
        }
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
        return $entity;
    }

    public function persist($entity)
    {
        $this->getEntityManager()->persist($entity);
    }

    public function flush()
    {
        $this->getEntityManager()->flush();
    }

    public function clear(): void
    {
        $this->getEntityManager()->clear();
    }

    public function delete($entities, $inTransaction = false)
    {
        if (!is_array($entities)) {
            $entities = [$entities];
        }
        foreach ($entities as $entity) {
            $this->getEntityManager()->remove($entity);
        }
        if (!$inTransaction) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection()
    {
        return $this->getEntityManager()->getConnection();
    }

    public function &__get($propertyName): mixed {
        preg_match('/([a-zA-Z0-9]+)Service/i', $propertyName, $serviceMatches);
        if (count($serviceMatches) > 0) {
            return $this
                ->serviceLocator
                ->get('App\Service\\' . ucfirst($serviceMatches[1]) . 'Service');
        }

        preg_match('/([a-zA-Z0-9]+)Repo/i', $propertyName, $repositoryMatches);
        if (count($repositoryMatches) > 0) {
            return $this
                ->serviceLocator
                ->get('App\Service\\' . ucfirst($repositoryMatches[1]) . 'Service')
                ->repository;
        }

        return parent::__get($propertyName);
        trigger_error('Could not found property ' . $propertyName . ' in ' . $this->commonService->getClassName($this), E_USER_ERROR);
    }

    public function getPagingData($queryBuilder, $config = null, $group = null, $returnCount = false)
    {
        
        if (is_null($config)) {
            $config = [
                'page' => 1,
                'limit' => 20
            ];
        }
        if (!isset($config['page'])) {
            $config['page'] = 1;
        }
        if (!isset($config['limit'])) {
            $config['limit'] = 20;
        } else if (((int)$config['limit']) <= 0) {
            $config['limit'] = pow(10, 3);
        }
        $queryBuilder
            ->setFirstResult(($config['page'] - 1) * $config['limit'])
            ->setMaxResults($config['limit']);
        $this->logger->info('final query builder', [$queryBuilder->getDQL()]);
        $this->logger->info('final query builder query', [$queryBuilder->getQuery()]);
        $entities = new Paginator($queryBuilder);
        $total = count($entities);
        if($returnCount) return $total;
        $list = [];
        foreach ($entities as $entity) {
            if ($group === 'ENTITY') {
                $list[] = $entity;
            } else {
                $context = [];
                if (!is_null($group)) {
                    $context['groups'] = $group;
                    if(isset($config['reportCurrency'])) {
                        $context['reportCurrency'] = $config['reportCurrency'];
                    }
                } else {
                    //$context['groups'] = '*';
                }
                $list[] = $this->serializer->normalize($entity, null, $context);
            }
        }
        if (isset($config['noPaging'])) {
            return $list;
        }
        return [
            'total' => $total,
            'totalPages' => ceil($total / $config['limit']),
            'list' => $list,
            'currentPage' => 1 * $config['page']
        ];
    }

    private function operatorCallbackMap () {
        return [
            'like' => function ($andx, $queryBuilder, $operator, $entityProperty, $paramName, $value) {
                $andx
                ->add(
                    $queryBuilder
                        ->expr()
                        ->like($entityProperty, ':' . $paramName)
                );
                $queryBuilder->setParameter($paramName, '%' . $value . '%');
            },
            'startsWith' => function ($andx, $queryBuilder, $operator, $entityProperty, $paramName, $value) {
                $andx
                ->add(
                    $queryBuilder
                        ->expr()
                        ->like($entityProperty, ':' . $paramName)
                );
                $queryBuilder->setParameter($paramName, $value . '%');
            },
            'isEmpty' => function ($andx, $queryBuilder, $operator, $entityProperty, $paramName, $value) {
                $andx
                ->add(
                    $queryBuilder
                        ->expr()
                        ->orX(
                            $queryBuilder->expr()->isNull($entityProperty),
                            $queryBuilder->expr()->eq($entityProperty, "''")
                        )
                );
            },
            'inArray' => function ($andx, $queryBuilder, $operator, $entityProperty, $paramName, $value) {
                $orx = $queryBuilder->expr()->orX();
                $delimiter = str_contains($value, '%2C') ? '%2C' : ',';
                $this->logger->info('in array check ', [$delimiter, $value]);
                forEach(explode($delimiter, $value) as $index => $val) {
                    $orName = (80 + $index) . $paramName;
                    $orx->add($queryBuilder->expr()->eq($entityProperty, ':sha' . $orName));
                    $queryBuilder->setParameter('sha' . $orName, $val);
                }
                $andx->add($orx);
            },
            'relativeRange' => function ($andx, $queryBuilder, $operator, $entityProperty, $paramName, $value) {
                
                list($from, $to) = DateRange::from($value)->range();
                $andx->add(
                    $queryBuilder
                        ->expr()
                        ->between($entityProperty, ':' . $paramName . 'from', ':' . $paramName . 'to')
                );
                $queryBuilder
                    ->setParameter($paramName . 'from', $from)
                    ->setParameter($paramName . 'to', $to);
            },
            'between' => function ($andx, $queryBuilder, $operator, $entityProperty, $paramName, $value) {
                list($from, $to) = explode(',', $value);
                $andx->add(
                    $queryBuilder
                        ->expr()
                        ->between($entityProperty, ':' . $paramName . 'from', ':' . $paramName . 'to')
                );
                $queryBuilder
                    ->setParameter($paramName . 'from', $from)
                    ->setParameter($paramName . 'to', $to);
            },
            'subOrX' => function ($andx, $queryBuilder, $operator, $entityProperty, $paramName, $value) {
                $orx = $queryBuilder->expr()->orX();
                forEach($value as $index => $condition) {
                    $orx->add($this->processFilter($condition, $queryBuilder, 40 + $index));
                }
                $andx->add($orx);
            },
        ];
    }

    public function processFilter($filter, $queryBuilder, $index) {
        // todo: only allow some type of operators here
        list($propertyConfig, $operator, $value) = $filter;
        $this->logger->info('process for filter', $filter);
        if(!is_array($value)) {
            $value = rawurldecode($value);
        }
        $props = explode(',', $propertyConfig);
        // this is for multiple props filter, anywhere filter or quick search
        // normaly only need one loop here
        $orx = $queryBuilder->expr()->orX();
        foreach($props as $pindex => $property) {
            $andx = $queryBuilder->expr()->andX();
            
            if(str_contains($property, '.')) {
                @list($rel1, $rel2, $rel3) = explode('.', $property);
                if(empty($rel3)) {
                    // special case for querying shipment of ShipmentActivity
                    if($this->getEntityName() === 'ShipmentActivity' && $rel1 === 'shipment') {
                        $relationTableAlias = $rel1;
                        if(!in_array($relationTableAlias, $queryBuilder->getAllAliases())) {
                            $queryBuilder
                            ->leftJoin('App\Entity\Shipment', 'shipment', Join::WITH, 'ShipmentActivity.shipmentId = shipment.id');
                        }
                    } else {
                        $relationTable = $this->getEntityName() . '.' . $rel1;
                        $relationTableAlias = $rel1 . $index;
                        if(!in_array($relationTableAlias, $queryBuilder->getAllAliases())) {
                            $queryBuilder->leftJoin($relationTable, $relationTableAlias);
                        }
                    }
                    
                    $relationProperty = $rel2;
                } else {
                    $relationTable = $this->getEntityName() . '.' . $rel1;
                    $relationTableAlias = $rel1 . $index;
                    if(!in_array($relationTableAlias, $queryBuilder->getAllAliases())) {
                        $queryBuilder->leftJoin($relationTable, $relationTableAlias);
                    }
                    $relationTableAlias = $relationTableAlias . $rel2;
                    if(!in_array($relationTableAlias, $queryBuilder->getAllAliases())) {
                        $queryBuilder->leftJoin($rel1 . $index. '.' . $rel2, $relationTableAlias);
                    }
                    $relationProperty = $rel3;
                }

                $entityProperty = $relationTableAlias . '.' . $relationProperty;
                $paramName = $relationProperty . '_' . $index . '_' . $pindex;
                $this->logger->info('have relation search here', [$entityProperty, $paramName]);
            } else {
                // the -> is used on embeded property
                $entityProperty = $this->getEntityName() . '.' . str_replace('->', '.', $property);
                $paramName = str_replace('->', '_', $property) . '_' . $index . '_' . $pindex;
            }
            
            $callbackMap = $this->operatorCallbackMap();
            if(in_array($operator, array_keys($callbackMap))) {
                $this->logger->info('callback for operator', [$operator]);
                $callbackMap[$operator]($andx, $queryBuilder, $operator, $entityProperty, $paramName, $value);
            } else {
                if($operator === 'isMemberOf') {
                    $andx->add($queryBuilder->expr()->$operator(':' . $paramName,$entityProperty));
                } else {
                    $andx->add($queryBuilder->expr()->$operator($entityProperty, ':' . $paramName));
                }
                
                $queryBuilder->setParameter($paramName, $value);
            }
            $orx->add($andx);
        }
        return $orx;
    }

    public function setFilterByOperator(QueryBuilder $queryBuilder, $requestData) 
    {
        if(empty($requestData['filters'])) return;
        foreach ($requestData['filters'] as $index => $filter) {
            if(is_null($filter)) continue;
            $orx = $this->processFilter($filter, $queryBuilder, $index);
            // todo: god forbid
            // an exception for query assigned user here
            list($propertyConfig, $operator, $value) = $filter;
            if($propertyConfig === 'createdBy,accountManager') {
                $paramName =  'assignedUsers_' . $index;
                $orx->add($queryBuilder->expr()->isMemberOf(':' . $paramName, $this->getEntityName() . '.assignedUsers'));
                $queryBuilder->setParameter($paramName, $value);
            } 
            $queryBuilder->andWhere($orx);

        }
    }

    public function setFilter(QueryBuilder $queryBuilder, $requestData)
    {
        
        foreach ($requestData as $key => $value) {
            if ($value === '') {
                continue;
            }

            if (strpos($key, 'filter_') === 0) {
                $filter = str_replace('filter_', '', $key);
                if (strpos($filter, '_like') !== false) {
                    $filter = str_replace('_like', '', $filter);
                    if ($filter === 'namefull') {
                        $queryBuilder
                            ->andWhere(
                                $queryBuilder
                                    ->expr()
                                    ->orX(
                                        $queryBuilder
                                            ->expr()
                                            ->like($this->getEntityName() . '.firstName', ':firstName'),
                                        $queryBuilder
                                            ->expr()
                                            ->like($this->getEntityName() . '.lastName', ':lastName')
                                    )
                            )
                            ->setParameter(':firstName', $value)
                            ->setParameter(':lastName', $value);
                    } else {
                        // or like
                        if (strpos($filter, '_or_') !== -1) {
                            $orx = $queryBuilder->expr()->orX();
                            foreach (explode('_or_', $filter) as $orFilter) {
                                $orx->add(
                                    $queryBuilder
                                        ->expr()
                                        ->like($this->getEntityName() . '.' . $orFilter, ':' . $orFilter)
                                );
                                $queryBuilder->setParameter($orFilter, '%' . $value . '%');
                            }
                            $queryBuilder->andWhere($orx);
                        } else {
                            $fieldName = $this->getEntityName() . '.' . $filter;
                            $queryBuilder->andWhere(
                                $queryBuilder
                                    ->expr()
                                    ->like($fieldName, ':' . $filter)
                            );
                            $queryBuilder->setParameter($filter, $value);
                        }
                    }
                } else {
                    $fieldName = $this->getEntityName() . '.' . $filter;
                    if (is_array($value)) {
                        $queryBuilder
                            ->andWhere(
                                $queryBuilder
                                    ->expr()
                                    ->in($fieldName, ':in' . $filter)
                            )
                            ->setParameter(
                                'in' . $filter,
                                $value,
                                ArrayParameterType::INTEGER
                            );
                    } else if ($value === 'falseOrNull') {
                        $queryBuilder->andWhere($fieldName . ' = 0 or ' . $fieldName . ' is NULL');
                    } else if ($value === 'isNull') {
                        $queryBuilder->andWhere($fieldName . ' is NULL');
                    } else if ($value === 'true' || $value === 'false') {
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->eq($fieldName, ':' . $filter)
                        );
                        $queryBuilder->setParameter($filter, $value === 'true');
                    } else if ($value === 'notNull') {
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->isNotNull($fieldName)
                        );
                    } else {
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->eq($fieldName, ':' . $filter)
                        );
                        $queryBuilder->setParameter($filter, $value);
                    }
                }
            } else if (strpos($key, 'except_') === 0) {
                $except = str_replace('except_', '', $key);
                $isInt = is_int(explode(',', $value)[0]);
                if ($isInt) {
                    $value = array_map('intval', explode(',', $value));
                } else {
                    $value = array_map('strval', explode(',', $value));
                }
                $fieldName = $this->getEntityName() . '.' . $except;

                $queryBuilder
                    ->andWhere(
                        $queryBuilder
                            ->expr()
                            ->orX(
                                $queryBuilder->expr()->isNull($fieldName),
                                $queryBuilder
                                    ->expr()
                                    ->notIn($fieldName, ':notIn' . $except)
                            )
                    )
                    ->setParameter(
                        'notIn' . $except,
                        $value,
                        $isInt
                            ? ArrayParameterType::INTEGER
                            : ArrayParameterType::STRING
                    );
            } else if (strpos($key, 'has_') === 0) {
                $paramName = 'p' . bin2hex(random_bytes(5));
                $has = str_replace('has_', '', $key);
                $relationName = explode('_', $has)[0];
                $relationProperty = explode('_', $has)[1];
                $fieldName = $this->getEntityName() . '.' . $relationName;
                $queryBuilder
                    ->leftJoin($fieldName, $paramName)
                    ->andWhere($paramName . '.' . $relationProperty . ' = :' . $paramName)
                    ->setParameter($paramName, $value);
            } else if (strpos($key, 'hasAny_') === 0) {
                $paramName = 'p' . bin2hex(random_bytes(5));
                $has = str_replace('hasAny_', '', $key);
                $relationName = explode('_', $has)[0];
                $relationProperty = explode('_', $has)[1];
                $fieldName = $this->getEntityName() . '.' . $relationName;
                $queryBuilder
                    ->leftJoin($fieldName, $paramName)
                    ->andWhere($paramName . '.' . $relationProperty . ' = :' . $paramName)
                    ->setParameter($paramName, $value);
            } else if (strpos($key, 'hasAny2_') === 0) {
                $paramName = 'p' . bin2hex(random_bytes(5));
                $has = str_replace('hasAny2_', '', $key);
                $relationName = explode('_', $has)[0];
                $relationProperty = explode('_', $has)[1];
                $fieldName = $this->getEntityName() . '.' . $relationName;
                $queryBuilder
                    ->leftJoin($fieldName, $paramName)
                    ->andWhere(':' . $paramName . ' member of ' . $paramName . '.' . $relationProperty)
                    ->setParameter($paramName, $value);
            } else if (strpos($key, 'memberOf_') === 0) {
                $paramName = 'p' . bin2hex(random_bytes(5));
                $relationName = str_replace('memberOf_', '', $key);
                $fieldName = $this->getEntityName() . '.' . $relationName;
                if ($value === 'isNull') {
                    $queryBuilder->andWhere($fieldName . ' is empty');
                } else {
                    $queryBuilder
                        ->leftJoin($fieldName, $paramName)
                        ->andWhere(':' . $paramName . ' member of ' . $fieldName)
                        ->setParameter($paramName, $value);
                }
            } else if (strpos($key, 'isOneOf_') === 0) {
                $paramName = 'p' . bin2hex(random_bytes(5));
                list($dummy, $parent, $parentProperty) = explode('_', $key);

                $queryBuilder->join(
                    'App\Entity\\' . $parent,
                    strtolower($parent),
                    Join::WITH,
                    $this->getEntityName() . ' member of ' . strtolower($parent) . '.' .$parentProperty 
                    )
                    ->andWhere(strtolower($parent) . '.id = :' . $paramName)
                    ->setParameter($paramName, $value);
                ;
            }
        }
    }

    public function setSort(QueryBuilder $queryBuilder, $requestData)
    {
        foreach ($requestData as $key => $value) {
            if (strpos($key, 'sort_') === false) {
                continue;
            }
            $direction = strpos($key, '_asc') !== false ? 'ASC' : 'DESC';
            $value = str_replace('Text', '', $value);
            $isRelation = strpos($value, 'Relation') !== false;
            if ($isRelation) {
                $value = str_replace('Relation', '', $value);
                @list($rel1, $rel2, $rel3) = explode('.', $value);
                if(empty($rel3)) {
                    $rel1Table = $this->getEntityName() . '.' . $rel1;
                    $queryBuilder->leftJoin($rel1Table, $rel1);
                    $queryBuilder->addOrderBy($value, $direction);
                } else {
                    $rel1Table = $this->getEntityName() . '.' . $rel1;
                    $queryBuilder->leftJoin($rel1Table, $rel1);
                    $rel2Table = $rel1 . '.' . $rel2;
                    $queryBuilder->leftJoin($rel2Table, $rel2);
                    $queryBuilder->addOrderBy($rel2 . '.' . $rel3, $direction);
                }
            } else {
                $fieldName = $this->getEntityName() . '.' . $value;
                $queryBuilder->addOrderBy($fieldName, $direction);
            }
        }
    }

    public function getList($requestData = [], $group = null, callable $extraFilter = null, $returnCount = false)
    {
        if ($requestData instanceof Request) {
            $requestData = $requestData->query->all();
        }
        $queryBuilder = $this->createQueryBuilder($this->getEntityName());
        if (!is_null($extraFilter)) {
            $extraFilter($queryBuilder);
        }

        $this->setFilter($queryBuilder, $requestData);
        $this->setFilterByOperator($queryBuilder, $requestData);
        
        $this->setSort($queryBuilder, $requestData);
        return $this->getPagingData($queryBuilder, $requestData, $group, $returnCount);
    }

    public function getAll($requestData, $DTO = null, callable $extraFilter = null)
    {
        if ($requestData instanceof Request) {
            $requestData = $requestData->query->all();
        }
        $queryBuilder = $this->createQueryBuilder($this->getEntityName());
        if (!is_null($extraFilter)) {
            $extraFilter($queryBuilder);
        }
        $this->setFilter($queryBuilder, $requestData);
        $this->setSort($queryBuilder, $requestData);

        return $queryBuilder->getQuery()->execute();
    }

    public function getEntityName(): string
    {
        return $this->commonService->getClassName($this, true);
    }
}