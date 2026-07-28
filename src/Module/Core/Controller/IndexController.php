<?php
namespace App\Module\Core\Controller;

use App\Misc\Attribute\AppModule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[AppModule('core')]
class IndexController extends AbstractController
{
    #[Route('/number/{max}', name: 'app_lucky_number')]
    public function number(int $max): Response
    {
        $number = random_int(0, $max);

        return $this->render('Homepage/index.html.twig', ['number' => $number]);

    }
}