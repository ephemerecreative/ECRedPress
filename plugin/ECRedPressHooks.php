<?php
require_once "../ECRedPressLogger.php";

/**
 * Class ECRedPressHooks
 *
 * Manages hooks.
 */
class ECRedPressHooks {
    public function __construct()
    {
        $this->registerSavePost();
    }

    /**
     * Delete cache after post saved.
     */
    private function registerSavePost()
    {
        function ecrpSavePost($post_id){
            ECRedPressLogger::getLogger()->plugin->info("About to delete post cache from save_post hook.");
            $ecrp = ECRedPress::getEcrp();
            $ecrp->deleteCache(get_permalink($post_id));
        }

        add_action( 'save_post', 'ecrpSavePost' );
    }
}