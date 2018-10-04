<?php
require_once __DIR__ . "/../ECRedPress.php";
require_once __DIR__ . "/../ECRedPressLogger.php";

class ECRedPressAdminHandlers
{
    /**
     * Renders the main admin page.
     */
    public function admin_main()
    {
        $ecrp = ECRedPress::get_ecrp();
        include __DIR__ . "/templates/admin_main.php";
    }

    /**
     * Clears the site cache.
     * @throws ECRedPressRedisParamsException
     */
    public function clear_cache()
    {
        $ecrp = ECRedPress::get_ecrp();
        $logger = ECRedPressLogger::get_logger();

        $args = array(
            'posts_per_page' => -1,
            'post_type' => 'any',
        );
        $the_query = new WP_Query($args);

        $home_url = get_home_url()."/";

        $permalinks = [$home_url];

        $ecrp->delete_cache($home_url);

        while ($the_query->have_posts()) {
            $the_query->the_post();

            array_push($permalinks, get_the_permalink());

            $ecrp->delete_cache(get_the_permalink());
        }

        include __DIR__ . "/templates/clear_cache.php";
    }
}