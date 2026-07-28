<?php
// src/Security/PostVoter.php
namespace App\Security;

use App\Module\Core\Entity\User;
use App\Module\Core\Service\UserService;
use Psr\Log\LoggerInterface;
use App\Module\Core\Enum\Permission;
use App\Module\Core\Service\RequestService;
use App\Module\Core\Enum\RequestMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ServiceCollectionInterface;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class PermissionVoter extends Voter
{
    public function __construct(
        protected UserService $userService,
        protected RequestService $requestService,
        protected RequestStack $requestStack,
        protected ServiceCollectionInterface  $serviceLocator,
        protected LoggerInterface $logger,
    )
    {
        
    }

    protected function supports(string $action, mixed $subject): bool
    {
        // if the attribute isn't one we support, return false
        $this->logger->info('is supports', [$action, $subject]);
        return in_array($action, RequestMethod::methodList());
    }

    protected function voteOnAttribute(string $action, mixed $subject, TokenInterface $token): bool
    {
        /** @var User $user */
        $user = $token->getUser();
        $this->logger->info('check user', [$user]);
        if (!$user instanceof User) {
            return false;
        }
        if(in_array("ROLE_ADMIN", $user->getRoles())) {
            return true;
        } 
        if(in_array(Permission::Admin, $user->getUserGroup()->getPermissions())) {
            return true;
        }
        
        return  $this->can($action, $subject, $user);
    }
    private function can(string $action, $subject, $user) {
        $actionMethod = RequestMethod::tryFrom($action) ?? $action;
        // for GET LIST
        if($actionMethod === RequestMethod::GET && $subject instanceof Request) {
            preg_match('/\\\\([a-zA-Z]+)Controller/m', $subject->attributes->get('_controller'), $match);
            $subjectClassName = $match[1];
            $publicList = [
                'CalculationType', 'Charge', 'Port', 'ExchangeRateGroup', 'Incoterm', 'Unit', 'Currency', 'Country', 'Contact', 'BankAccount', 'InvoiceInfo', 'CustomChargeType'
            ];
            if(in_array($subjectClassName, $publicList)) return true;
            if(!$this->userService->canUser($actionMethod, $match[1], $subject)) return false;

            
            // here because nowhere else to put it :)
            $ownershipFields = $this->requestService->getOwnershipFields($subject);
            // no need to check, return true
            if(empty($ownershipFields)) return true;

            $filters = $subject->query->all()['filters'] ?? [];  
            $filters[] = [$ownershipFields, 'eq', $user->getId()];
            $subject->query->set('filters', $filters);
            return true;
        }
        // for GET ONE: always allow, access is already gated by entity existence
        if ($actionMethod === RequestMethod::GET) {
            return true;
        }
        // for POST + PUT + PATCH
        if (in_array($actionMethod, RequestMethod::updateMethodList()) && $subject instanceof MapRequestPayload) {
            $entityName = explode('\\', $subject->metadata->getType());
        }
        // DELETE
        else {
            $entityName = explode('\\', get_class($subject));
        }
        $entityName = end($entityName);
        return $this->userService->canUser($actionMethod, $entityName, $this->requestStack->getCurrentRequest());
    }
}