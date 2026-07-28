<?php

namespace App\Misc\Exception;
 
use Exception;
use Symfony\Component\HttpFoundation\Response;
 
class BadRequestException extends Exception implements ApiExceptionInterface
{
    const DUPLICATE_ENTITY = 'This object exists already';
    const NO_ENTITY_TO_DELETE = 'Can not delete, this object is not found';
    const NO_PERMISSION_OF_ENTITY = 'You have no permission to edit this';
    const NO_ENTITY = 'Entity not found';
    const ENTITY_DELETE_RELATION = 'Can not delete this object, please remove all relations then retry';
    const WRONG_INPUT = 'Inputs are incorrect';
    const DUPLICATE_REQUEST = 'Can not process this request twice';
    const WRONG_SYNC_KEY = 'Can not find matched site for this key';
    const SEND_MAIL_ERROR = 'Can not send email';
    
    protected $messages = [];
    
    public function __construct(
        String $error, 
        String $action = null, 
        String $entityName = null, 
        String $entityId = null, 
        Array $data = []
    ) {
        $this->messages = [
          'error' => $error,
          'action' => $action,
          'entityName' => $entityName,
          'entityId' => $entityId,
          'code' => Response::HTTP_BAD_REQUEST,
          'data' => $data
        ];
        parent::__construct($error, Response::HTTP_BAD_REQUEST);
    }
    
    public function getMessages()
    {
        return $this->messages;
    }
}
