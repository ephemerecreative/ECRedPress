<?php
/**
 * Front to the WordPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells WordPress to load the theme.
 *
 * @package WordPress
 */

if (getenv('REDISCLOUD_URL')) {
    $redis_url = getenv('REDISCLOUD_URL');
    putenv("ECRP_REDIS_URL=$redis_url");
}
require_once __DIR__ . "/wp-content/plugins/ECRedPress/ECRedPress.php";
$ecrpLoaded = false;
try {
    $ecrp = ECRedPress::get_ecrp();
    $ecrp->start_cache();
    $ecrpLoaded = true;
} catch (ECRedPressRedisParamsException $e) {
    error_log($e->getMessage());
}
/**
 * Tells WordPress to load the WordPress theme and output it.
 *
 * @var bool
 */
define('WP_USE_THEMES', true);

/** Loads the WordPress Environment and Template */
require(dirname(__FILE__) . '/wp-blog-header.php');


try {
    if ($ecrpLoaded) {
        $ecrp->end_cache();
    }
} catch (ECRedPressRedisParamsException $e) {
    error_log($e->getMessage());
}
