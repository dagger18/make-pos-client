<?php

namespace App\Misc\Doctrine;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Exception\RetryableException;

class CustomEntityManager extends EntityManager
{

    public function __construct(
        private EntityManagerInterface $em,
        private ManagerRegistry $mr,
        private LoggerInterface $logger
    )
    {}

    public function transactional(callable $process)
    {
        $retries = 0;
        $error = null;
        do {
            $this->beginTransaction();
            
            try {
                $ret = $process();
                $this->flush();
                $this->commit();
                return $ret;
            } catch (\Exception $e) {
                $this->rollback();
                $this->close();
                $this->resetManager();
                ++$retries;
                $error = $e;
                $this->logger->info('error take ' . $retries, [$error]);
            }
        } while ($retries < 5);
        throw $error;
    }

    public function resetManager()
    {
        $this->em = $this->mr->resetManager();
    }

    public function __call($name, $args) {
        return call_user_func_array([$this->em, $name], $args);
    }
}