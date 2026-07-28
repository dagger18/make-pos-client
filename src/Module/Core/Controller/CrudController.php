<?php

namespace App\Module\Core\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Core\Service\BaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[AppModule('core')]
class CrudController extends AbstractController
{
    public function __construct(
        protected BaseService $baseService
    )
    {
    }
    public function __get($propertyName) {
        if($propertyName === 'repository') {
            $entityName = ucfirst($this->baseService->commonService->getClassName($this, true));
            return $this
                ->baseService->serviceLocator
                ->get('App\Service\\' . $entityName . 'Service')
                ->repository;
        }
        if($propertyName === 'service') {
            $entityName = ucfirst($this->baseService->commonService->getClassName($this, true));
            return $this
                ->baseService->serviceLocator
                ->get('App\Service\\' . $entityName . 'Service');
        }

        trigger_error('Could not found property ' . $propertyName . ' in ' . $this->baseService->commonService->getClassName($this), E_USER_ERROR);
    }
}