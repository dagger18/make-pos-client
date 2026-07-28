<?php

namespace App\Misc\Exception;
 
use Exception;
use Symfony\Component\HttpFoundation\Response;
 
class AccessDeniedException extends Exception implements ApiExceptionInterface
{
    const NOT_PERMITTED = 'User not found or banned';
    const USER_NOT_FOUND = 'Username or password is incorrect';
    const WRONG_TOKEN = 'Auth token does not match with any user';
    const TOKEN_NULLIFIED = 'TOKEN_NULLIFIED';
    const INVALID_TOKEN = 'Auth token is missing or incorrect';
    const INVALID_PERMISSION = 'Not enough access rights to perform this operation';
    const UNKNOWN = 'unknown';
    
    protected $messages = [];
    
    public function __construct(
        string $error = 'not permitted',
        string $action = self::UNKNOWN
    ) {
        $this->messages = [
          'error' => $error,
          'action' => $action,
          'code' => Response::HTTP_FORBIDDEN,
        ];
        parent::__construct($error, Response::HTTP_FORBIDDEN);
    }
    
    public function getMessages()
    {
        return $this->messages;
    }
}