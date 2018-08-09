<?php
/*
Plugin Name: ECRedPress
Plugin URI: https://ephemerecreative.ca
Description: Redis-based WordPress caching plugin.
Author: Raphaël Titsworth-Morin
Version: 0.1.0
Author URI: http://ephemerecreative.ca

Copyright (c) 2018 Raphaël Titsworth-Morin. All rights reserved.
*/

/**
 * @property ECRedPressHooks hookManager
 */
class ECRedPressPlugin {
    public function __construct()
    {
        require_once "ECRedPress.php";
        require_once "ECRedPressHooks.php";
        $this->hookManager = new ECRedPressHooks();
    }
}

new ECRedPressPlugin();
