<?php

namespace App\Module\Core\Repository;

use App\Module\Core\Entity\Media;
use App\Module\Core\Repository\BaseRepository;

class MediaRepository extends BaseRepository
{
    /** Media uploaded to S3 but not yet confirmed on the backup hub */
    public function findNeedingBackupSync(int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.s3Success = true')
            ->andWhere('m.backupSuccess IS NULL OR m.backupSuccess = false')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Media uploaded to backup but not yet confirmed on S3 */
    public function findNeedingS3Sync(int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.backupSuccess = true')
            ->andWhere('m.s3Success IS NULL OR m.s3Success = false')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
