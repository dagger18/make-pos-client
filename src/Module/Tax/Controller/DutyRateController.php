<?php
namespace App\Module\Tax\Controller;

use App\Module\Core\Controller\CrudController;

use App\Module\Tax\Entity\DutyRate;
use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PostActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/duty-rate')]
#[IsGranted('ROLE_USER')]
#[AppModule('tax')]
class DutyRateController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;
}
