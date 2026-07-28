<?php
namespace App\Module\Crm\Controller;

use App\Module\Core\Controller\CrudController;

use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PostActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contact')]
#[IsGranted('ROLE_USER')]
#[AppModule('crm')]
class ContactController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;
}
