<?php
/*
Plugin Name: ECRedPress
Plugin URI: https://ephemerecreative.ca
Description: Redis-based WordPress caching plugin.
Author: Raphaël Titsworth-Morin
Version: 0.1.0
Author URI: http://ephemerecreative.ca
License: GPL-2.0-or-later

ECRedPress. A simple system to cache WordPress pages to Redis.
Copyright (C) 2018  Raphaël Titsworth-Morin

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

require_once "./plugin/ECRedPressHooks.php";
require_once "./plugin/ECRedPressAdmin.php";

/**
 * @property ECRedPressHooks hookManager
 * @property ECRedPressAdmin adminManager
 */
class ECRedPressPlugin {
    public function __construct()
    {
        $this->hookManager = new ECRedPressHooks();
        $this->adminManager = new ECRedPressAdmin();
    }
}

new ECRedPressPlugin();
