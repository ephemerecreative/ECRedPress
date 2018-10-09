<?php
require_once __DIR__ . "/../ECRedPress.php";
require_once __DIR__ . "/../ECRPLogger.php";
require_once __DIR__ . "/ECRPOptions.php";

class ECRPAdminHandlers
{
    /**
     * Renders the main admin page.
     * @throws ECRedPressRedisParamsException
     */
    public static function admin_main()
    {
        $ecrp = ECRedPress::get_ecrp();
        $cached_urls = get_option(ECRPOptions::get_cached_urls_key(), []);
        include __DIR__ . "/templates/admin_main.php";
    }

    /**
     * Clears the site cache.
     * @throws ECRedPressRedisParamsException
     */
    public static function clear_cache()
    {
        $ecrp = ECRedPress::get_ecrp();
        $logger = ECRPLogger::get_logger();

        $saved_urls = get_option(ECRPOptions::get_cached_urls_key(), []);

        $args = array(
            'posts_per_page' => -1,
            'post_type' => 'any',
        );
        $the_query = new WP_Query($args);

        $home_url = get_home_url()."/";

        $permalinks = [
            $home_url => true,
        ];

        while ($the_query->have_posts()) {
            $the_query->the_post();
            $permalinks[get_the_permalink()] = true;
        }

        $to_delete = array_merge($permalinks, $saved_urls);

        foreach ($to_delete as $url => $value) {
            $ecrp->delete_cache($url);
        }

        update_option(ECRPOptions::get_cached_urls_key(), []);

        include __DIR__ . "/templates/clear_cache.php";
    }
}