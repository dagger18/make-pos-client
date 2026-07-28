<?php
namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Entity\User;
use App\Module\Finance\Enum\ChargeType;
use App\Module\Core\Enum\Magnum;
use App\Module\Core\Enum\PageType;
use App\Module\Core\Enum\Permission;
use App\Module\Core\Enum\RequestMethod;
use App\Module\Core\Enum\UserStatus;
use App\Module\Core\Repository\UserRepository;
use App\Module\Crm\Service\ClientService;
use App\Module\Core\Service\MailService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
/**
 * Class UserService
 * @package App\Service
 */
class UserService extends BaseService
{
    /**
     * UserService constructor.
     * @param UserRepository $repository
     */
    public function __construct(
        protected BaseService $baseService,
        public UserRepository $repository,
        protected UserPasswordHasherInterface $passwordHasher,
        protected MailService $mailService,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function getUserFromToken ($token, $status): ?User {
        $users = $this->repository->getAll(['filter_status' => $status->value], 'ENTITY');
        forEach($users as $user) {
            if(hash_equals(hash('sha256', $user->getPassword()), $token)) {
                return $user;
            }
        }
        return null;
    }

    public function invite (User $user) {
        $user->setFirstName('');
        $user->setLastName('');
        $user->setRoles(['ROLE_USER']);
        $user->setStatus(UserStatus::Pending);
        $rawPassword = $this->commonService->randomString(6);
        $hashPassword = $this->passwordHasher->hashPassword($user, $rawPassword);
        $user->setPassword($hashPassword);
        $this->mailService->send(
            $user->getEmail(),
            'You have been invited to join the team',
            'email/inviteUser.html.twig',
            ['token' => hash('sha256', $hashPassword)]
        );
        return $this->repository->save($user);
    }
    public function forgotPassword (User $user) {
        $this->mailService->send(
            $user->getEmail(), 
            'You requested to reset your password', 
            'email/forgot_password.html.twig', 
            ['token' => hash('sha256', $user->getPassword())]
        );
    }

    public function finishRegister ($user, $data): User {
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setTitle($data['title']);
        $user->setRoles(['ROLE_USER']);
        $user->setStatus(UserStatus::Active);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $this->repository->save($user);
        return $user;
    }

    public function getUserPermissions (User $user): array {
        if(in_array("ROLE_ADMIN", $user->getRoles())
            || in_array(Permission::Admin, $user->getUserGroup()->getPermissions())
        ) {
            return [Permission::Admin];
        }
        return $user->getUserGroup()->getPermissions();
    }
    public function getAbilities (User $user): array {
        $permissions = $this->getUserPermissions($user);
        $abilities = [];
        /** @var Permission $permission */
        forEach($permissions as $permission) {
          $abilities[] = $permission->getAbility();
        }
        return $abilities;
    }

    public function canUser(RequestMethod|string $action, string $subject, ?Request $request = null): bool {
        $this->logger->info('check permission for user', ['action' => $action, 'subject' => $subject, 'request' => $request]);
        $permissions = $this->getUserPermissions($this->getUser());
        /** @var Permission $permission */
        forEach($permissions as $permission) {
            $ability = $permission->getAbility();
            if($permission === Permission::Admin) {
                return true;
            }
            if($ability['subject'] === $subject) {
                if($ability['action'] === 'MANAGE' || $ability['action'] === ($action instanceof RequestMethod ? $action->value : $action))
                    return true;
                if(($subject === 'Rate' || $subject === 'Charge') && $request !== null) {
                    if($action === RequestMethod::GET && str_starts_with($ability['action'], 'MANAGE_'))
                        return true;

                    if($chargeType = $request->query->get('filter_chargeType') 
                                      ?? $request->request->get('chargeType')
                    ) {
                      if( strtolower($ability['action']) === 'manage_'. strtolower(ChargeType::tryFrom($chargeType)?->name))
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function ping() {
        return [
        ];
    }

    public function viewPage(string $page) {
        $pageType = PageType::from($page);
        $pageString = ucfirst(str_replace('manage-', '', $pageType->value));
        $user = $this->getUser();
        $user->{'setLast' . $pageString . 'View'}(new \DateTime());
        $this->repository->save($user);
        return ['result' => true];
    }
}
