<?php

/**
 * Class ECRedPressException
 * @author Raphael Titsworth-Morin
 *
 * Exception to handle ECRedPress errors.
 */
class ECRedPressException extends Exception {
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class ECRedPressRedisParamsException extends ECRedPressException {
    public function __construct()
    {
        parent::__construct("Missing host and/or port. You need to either properly set ECRP_REDIS_URL or pass in a custom config array with REDIS_HOST, REDIS_PORT, and (optionally) REDIS_PASSWORD.", 1);
    }
}

class ECRedPressBadConfigException extends ECRedPressException {
    public function __construct()
    {
        parent::__construct("Bad configuration. Take a look at the docs to make sure you are using an appropriate setting and value for it.", 2);
    }
}