<?php
require_once __DIR__."/vendor/autoload.php";

use Monolog\Logger;

/**
 * @property Logger engine
 * @property Logger plugin
 */
class ECRedPressLogger {
    private static $instance = null;

    private function __construct()
    {
        $this->engine = new Logger('ECRPEngine');
        $this->plugin = new Logger('ECRPPlugin');
    }

    public static function getLogger()
    {
        return self::$instance ? self::$instance : new ECRedPressLogger();
    }
}

$ecrpLogger = ECRedPressLogger::getLogger();

