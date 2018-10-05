<?php
require_once __DIR__ . "/../ECRedPressLogger.php";
require_once __DIR__ . "/../ECRedPress.php";

/**
 * Class ECRedPressHooks
 *
 * Manages hooks.
 */
class ECRedPressHooks
{
    public function __construct()
    {
        $this->registerSavePost();
    }

    /**
     * Delete cache after post saved.
     */
    private function registerSavePost()
    {
        add_action('save_post', function ($post_id) {
            ECRedPressLogger::get_logger()->plugin->info("About to delete post cache from save_post hook.");
            $ecrp = ECRedPress::get_ecrp();
            $ecrp->delete_cache(get_permalink($post_id));
        });
    }
}