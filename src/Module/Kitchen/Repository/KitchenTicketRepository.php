<?php
namespace App\Module\Kitchen\Repository;

use App\Module\Core\Repository\BaseRepository;
use App\Module\Kitchen\Entity\KitchenTicket;
use Doctrine\Persistence\ManagerRegistry;
use App\Module\Core\Service\CommonService;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class KitchenTicketRepository extends BaseRepository
{
    public function __construct(
        ManagerRegistry $registry,
        CommonService $commonService,
        ContainerInterface $container,
        CacheInterface $appCache,
    ) {
        parent::__construct($registry, $commonService, $container, $appCache, KitchenTicket::class);
    }
}
