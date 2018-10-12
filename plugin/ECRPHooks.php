<?php
require_once __DIR__ . "/../ECRPLogger.php";
require_once __DIR__ . "/../ECRedPress.php";
require_once __DIR__ . "/ECRPOptions.php";
require_once __DIR__ . "/ECRPHelpers.php";

/**
 * Class ECRedPressHooks
 *
 * Manages hooks to add WP-related functionality to the cache.
 */
class ECRPHooks
{
    public function __construct()
    {
        $this->logger = ECRPLogger::get_logger()->plugin;
        $this->register_save_post();
        $this->set_cache_exp_on_request();
        $this->register_cached_url();
    }

    /**
     * Delete cache after post saved, so cached value stays up to date.
     */
    private function register_save_post()
    {
        add_action('save_post', function ($post_id) {
            ECRPLogger::get_logger()->plugin->info("About to delete post cache from save_post hook.");
            $ecrp = ECRedPress::get_ecrp();
            $ecrp->delete_cache(get_permalink($post_id));
        });
    }

    /**
     * Set appropriate cache expiration based on:
     *      Constants
     *      Plugin Settings
     *      Type of view (single page vs archive, etc.)
     *      Post Meta
     */
    private function set_cache_exp_on_request()
    {
        add_action('wp', function () {
            $ecrp = ECRedPress::get_ecrp();
            $ecrp->set_cache_expiration(ECRPHelpers::get_cache_exp());
        });
    }

    /**
     * Save cached urls to a WP option.
     * This is useful so we can easily clear each of them from cache in the future.
     */
    private function register_cached_url()
    {
        add_action('wp', function ($wp) {
            $ecrp = ECRedPress::get_ecrp();
            $ecrp_enabled = $ecrp->is_cache_enabled();
            $should_skip_cache = $ecrp->should_skip_cache();
            if (!$ecrp_enabled || $should_skip_cache)
                return;

            $url = $ecrp->get_config_option('CURRENT_URL');
            $cached_urls = get_option(ECRPOptions::get_cached_urls_key(), []);
            $cached_urls[$url] = [
                "Single Post"       => is_single() ? "True" : "False",
                "Cached At"         => date(DateTime::ISO8601),
                "Cache Expiration"  => ECRPHelpers::get_cache_exp(),
            ];
            update_option(ECRPOptions::get_cached_urls_key(), $cached_urls);
        });
    }
}