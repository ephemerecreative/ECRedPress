<?php
require_once __DIR__ . '/ECRPConstants.php';

class ECRPOptions
{
    public static function get_cached_urls_key()
    {
        return ECRPConstants::ECRP_CACHED_URLS_KEY;
    }

    public static function get_single_cache_exp()
    {
        return ECRPConstants::ECRP_IS_SINGLE_CACHE_EXP;
    }

    public static function get_not_single_cache_exp()
    {
        return ECRPConstants::ECRP_NOT_SINGLE_CACHE_EXP;
    }
}