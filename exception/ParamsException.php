<?php

namespace app\exception;

use Exception;
use app\constants\InternalAPI;

class ParamsException extends Exception
{
    public function __construct($message = "", Exception $previous = null)
    {
        parent::__construct($message, InternalAPI::PARAMS_ERROR, $previous);
    }
}