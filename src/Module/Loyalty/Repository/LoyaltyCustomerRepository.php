<?php
namespace App\Module\Loyalty\Repository;

use App\Module\Core\Repository\BaseRepository;
use App\Module\Loyalty\Entity\LoyaltyCustomer;
use Doctrine\Persistence\ManagerRegistry;
use App\Module\Core\Service\CommonService;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoyaltyCustomerRepository extends BaseRepository
{
    public function __construct(
        ManagerRegistry $registry,
        CommonService $commonService,
        ContainerInterface $container,
        CacheInterface $appCache,
    ) {
        parent::__construct($registry, $commonService, $container, $appCache, LoyaltyCustomer::class);
    }
}
