<?php
namespace App\Module\Inventory\Repository;

use App\Module\Core\Repository\BaseRepository;
use App\Module\Core\Service\CommonService;
use App\Module\Inventory\Entity\StockLevel;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class StockLevelRepository extends BaseRepository
{
    public function __construct(
        ManagerRegistry $registry,
        CommonService $commonService,
        ContainerInterface $container,
        CacheInterface $appCache,
    ) {
        parent::__construct($registry, $commonService, $container, $appCache, StockLevel::class);
    }
}
