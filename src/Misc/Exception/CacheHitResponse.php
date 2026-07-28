<?php

namespace App\Misc\Exception;
 
use Exception;
use Symfony\Component\HttpFoundation\Response;
 
class CacheHitResponse extends Exception
{
    public function __construct($data, $code)
    {
        parent::__construct($data, (int) $code);
    }
}
