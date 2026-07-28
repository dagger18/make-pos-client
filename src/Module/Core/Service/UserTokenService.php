<?php
namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Entity\User;
use App\Module\Core\Entity\UserToken;
use App\Module\Core\Repository\UserTokenRepository;
use Symfony\Component\Uid\Factory\UuidFactory;
/**
 * Class UserTokenService
 * @package App\Service
 */
class UserTokenService extends BaseService
{
    /**
     * UserTokenService constructor.
     * @param UserTokenRepository $repository
     */
    public function __construct(
        protected BaseService $baseService,
        public UserTokenRepository $repository,
        protected UuidFactory $uuidFactory,
        protected ConfigService $configService,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function getUserToken(User $user, $useOldToken = true) {
        if($useOldToken) {
            return $this->getOldToken($user);
        }
        return $this->createToken($user);
    }

    public function createToken(User $user) {
        $policy = (int) ($this->configService->getConfigValue('concurrent_login_policy') ?? 0);
        if ($policy > 0) {
            $existing = $this->repository->findBy(['user' => $user, 'nullified' => false]);
            if (count($existing) >= $policy) {
                foreach ($existing as $t) {
                    $t->setNullified(true);
                    $this->repository->save($t);
                }
            }
        }

        $token = $this->uuidFactory->randomBased()->create()->toRfc4122();
        $userToken = new UserToken();
        $userToken->setUser($user);
        $userToken->setToken($token);
        $userToken->setExpiresAt(time() + 10 * 24 * 60 * 60);
        $this->repository->save($userToken);
        return $token;
    }

    public function getOldToken(User $user) {
        /** @var UserToken $userToken */
        $userToken = $this->repository->findOneBy(['user' => $user]);
        if(!$userToken) return $this->createToken($user);
        return $userToken->getToken();
    }
}
