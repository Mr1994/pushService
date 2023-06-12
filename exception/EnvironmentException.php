<?php

namespace app\exception;

use Exception;
use app\InternalAPI;

class EnvironmentException extends Exception
{
    public function __construct($message = "", Exception $previous = null)
    {
        parent::__construct($message, InternalAPI::ENVIROMENT_ERROR, $previous);
    }
}