<?php
require_once __DIR__ . "/ECRPOptions.php";

class ECRPHelpers
{
    /**
     * Used in `wp` action. Returns the appropriate expiration time for the current page.
     * @return int
     */
    public static function get_cache_exp()
    {
        $single = is_single();

        if ($single) {
            $cache_exp = get_post_meta(get_the_ID(), 'ECRP_CACHE_EXP', true);
            return (int)($cache_exp ?: ECRPOptions::get_single_cache_exp());
        }
        else {
            return (int)(ECRPOptions::get_not_single_cache_exp());
        }
    }
}