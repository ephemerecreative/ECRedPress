<?php
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/ECRPException.php";
require_once __DIR__ . "/ECRPLogger.php";

/**
 * Class ECRedPress
 * @author Raphael Titsworth-Morin
 * @see https://github.com/markhilton/redis-http-cache
 * @see https://gist.github.com/JimWestergren/3053250#file-index-with-redis-php
 * @see http://www.jeedo.net/lightning-fast-wordpress-with-nginx-redis/
 *
 * Inspired by code and ideas from Jim Westergren, Jeedo Aquino, and Mark Hilton.
 *
 */
class ECRedPress
{
    /**
     * @var \Predis\Client $client Is the Predis client used to interact with Redis.
     */
    private $client;
    /**
     * @var array $custom_config The configuration overrides provided by other code.
     */
    private $custom_config = [];
    /**
     * @var null|ECRedPress $instance The active ECRP instance.
     */
    private static $instance = null;

    /**
     * ECRedPress constructor.
     * @param $config
     * @throws ECRedPressRedisParamsException
     */
    private function __construct($config)
    {
        if ($config)
            $this->custom_config = $config;
        self::$instance = $this;
        $this->init();
    }

    /**
     * Return this and set config.
     * @param array $custom_config
     * @return $this
     */
    public function set_config($custom_config)
    {
        if (is_array($custom_config))
            $this->custom_config = array_merge($this->custom_config, $custom_config);
        return $this;
    }

    /**
     * @param $option
     * @param $value
     * @throws ECRedPressException
     */
    public function set_config_option(string $option, $value)
    {
        if (!isset($option) || !isset($value)) {
            throw new ECRedPressBadConfigException();
        }
        $this->custom_config[$option] = $value;
    }

    /**
     * @param $option
     * @return mixed
     * @throws ECRedPressException
     */
    public function get_config_option(string $option)
    {
        return $this->get_config()[$option];
    }

    /**
     * Set expiration time for this object.
     * @param int $seconds
     * @throws ECRedPressException
     */
    public function set_cache_expiration(int $seconds)
    {
        ECRPLogger::get_logger()->engine->info("Setting cache expiration to: " . $seconds);
        $this->set_config_option('CACHE_EXPIRATION', $seconds);
    }

    /**
     * Return the config object.
     * @return array
     * @throws ECRedPressRedisParamsException
     */
    public function get_config()
    {
        return $this->_config();
    }

    /**
     * Gets the current EC RedPress instance or creates one.
     * @param array $customConfig
     * @return ECRedPress
     * @throws ECRedPressRedisParamsException
     */
    public static function get_ecrp($customConfig = null)
    {
        if (self::$instance != null) {
            return self::$instance->set_config($customConfig);
        } else {
            return new ECRedPress($customConfig);
        }
    }

    /**
     * Initialize ECRedPress instance.
     * @throws ECRedPressRedisParamsException
     */
    private function init()
    {
        $this->init_client();
    }

    /**
     * @param array $override
     * @return array
     * @throws ECRedPressRedisParamsException
     */
    private function _config($override = [])
    {
        $redisUrl = getenv('ECRP_REDIS_URL');
        $redis = $redisUrl ? parse_url($redisUrl) : [];
        $config = array_merge([
            'REDIS_HOST' => isset($redis['host']) ? $redis['host'] : null,
            'REDIS_PORT' => isset($redis['port']) ? $redis['port'] : null,
            'REDIS_PASSWORD' => isset($redis['pass']) ? $redis['pass'] : null,
            'CACHE_QUERY' => $this->_config_build_cache_query(),
            'CURRENT_URL' => $this->_config_build_current_url($this->_config_build_cache_query()),
            'CACHEABLE_METHODS' => $this->_config_build_cacheable_methods(),
            'CACHE_EXPIRATION' => $this->_config_build_cache_expiration(),
            'NOCACHE_REGEX' => $this->_config_build_nocache_regex()
        ], $this->custom_config, $override);

        if (!$config['REDIS_HOST'] || !$config['REDIS_PORT']) {
            throw new ECRedPressRedisParamsException();
        }

        return $config;
    }

    /**
     * Initialize Predis client.
     * @throws ECRedPressRedisParamsException
     */
    private function init_client()
    {
        $config = [
            'host' => $this->_config()['REDIS_HOST'],
            'port' => $this->_config()['REDIS_PORT'],
        ];
        if (isset($this->_config()['REDIS_PASSWORD']))
            $config['password'] = $this->_config()['REDIS_PASSWORD'];

        $this->client = new Predis\Client($config);
    }

    /**
     * Check if we are logged in.
     * @return bool
     */
    private function is_logged_in()
    {
        return !!preg_match("/wordpress_logged_in/", var_export($_COOKIE, true));
    }

    /**
     * Check if we're running from the CLI in which case, bypass the cache.
     * @return bool
     */
    private function is_cli()
    {
        return defined('WP_CLI');
    }

    /**
     * Is the cache enabled.
     * @return array|false|string
     */
    public function is_cache_enabled()
    {
        return !!getenv('ECRP_ENABLED');
    }

    /**
     * Check if request is a comment submission.
     * @return bool
     */
    private function is_comment_submission()
    {
        return (isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'max-age=0');
    }

    /**
     * Check if request is a comment submission.
     * @return bool
     */
    private function is_refresh()
    {
        return (isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'no-cache');
    }

    /**
     * Check if the request method is of a type we want to cache.
     * @return bool
     * @throws ECRedPressRedisParamsException
     */
    private function is_cacheable_method()
    {
        return in_array($_SERVER['REQUEST_METHOD'], $this->_config()['CACHEABLE_METHODS']);
    }

    /**
     * Checks if this url should be cached.
     * @return bool
     * @throws ECRedPressRedisParamsException
     */
    public function is_cacheable_url()
    {
        $config = $this->_config();
        print_r($config);
        foreach ($config['NOCACHE_REGEX'] as $pattern) {
            if (preg_match("/$pattern/", $config['CURRENT_URL'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get the base key for a given url. Takes into account whether or not we are caching based on query string.
     * @param null $url
     * @return string
     * @throws ECRedPressRedisParamsException
     */
    private function get_url_key($url = null)
    {
        if (!$url) {
            $url = $this->_config()['CURRENT_URL'];
        }
        $url = $this->should_cache_query() ? strtok($url, '?') : $url;
        return md5($url);
    }

    /**
     * Builds the current url for config.
     * @return string
     */
    private function _config_build_current_url($cache_query_string)
    {
        $url = sprintf(
            "%s://%s%s",
            $this->get_protocol(),
            $_SERVER['HTTP_HOST'],
            $_SERVER['REQUEST_URI']
        );
        return $cache_query_string ? $url : strtok($url, "?");
    }

    /**
     * Return cacheable methods to config.
     * Defaults to ['GET']
     * @return array
     */
    private function _config_build_cacheable_methods()
    {
        $methods = null;

        if (getenv('ECRP_CACHEABLE_METHODS')) {
            $methods = array_map(function ($method) {
                return trim(strtoupper($method));
            }, explode(',', $methods));
        }

        if (defined('ECRP_CACHEABLE_METHODS')) {
            $methods = ECRP_CACHEABLE_METHODS;
        }

        return $methods ?: ['GET'];
    }

    /**
     * Build cache expiration time for config.
     * Defaults to 900 seconds.
     * @return int
     */
    private function _config_build_cache_expiration()
    {
        $cacheExp = null;

        if (getenv('ECRP_DEFAULT_EXPIRATION')) {
            $cacheExp = (int)(getenv('ECRP_DEFAULT_EXPIRATION'));
        }

        if (defined('ECRP_DEFAULT_EXPIRATION')) {
            $cacheExp = ECRP_DEFAULT_EXPIRATION;
        }

        return $cacheExp ?: 900;
    }

    /**
     * Build cache query setting for config. (whether or not query strings are cached)
     * Defaults to false.
     * @return bool
     */
    private function _config_build_cache_query()
    {
        $cache_query = null;

        if (getenv('ECRP_CACHE_QUERY')) {
            $cache_query = getenv('ECRP_CACHE_QUERY') == 'true';
        }

        if (defined('ECRP_CACHE_QUERY')) {
            $cache_query = ECRP_CACHE_QUERY;
        }

        return $cache_query ?: false;
    }

    /**
     * Build the list of regexes defining things not to cache.
     * Defaults to [
     *      '.*\/wp-admin\/.*',
     *      '.*\/wp-login\.php$',
     * ]
     * @return array
     */
    private function _config_build_nocache_regex()
    {
        $regexes = [
            '.*\/wp-admin\/.*',
            '.*\/wp-login\.php$',
        ];

        if (getenv('ECRP_NOCACHE_REGEX')) {
            array_merge($regexes, str_split(getenv('ECRP_CACHE_QUERY'), "@@@"));
        }

        if (defined('ECRP_NOCACHE_REGEX')) {
            array_merge($regexes, ECRP_NOCACHE_REGEX);
        }

        return $regexes;
    }

    /**
     * Returns the protocol, either through X_FORWARDED header if set, or HTTPS.
     * @return string
     */
    private function get_protocol()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']))
            return $_SERVER['HTTP_X_FORWARDED_PROTO'];
        else
            return $_SERVER['HTTPS'] ? 'https' : 'http';
    }

    /**
     * Return sub key for a url (status, headers, etc.)
     * @param $sub
     * @param null $url
     * @return string
     * @throws ECRedPressRedisParamsException
     */
    private function get_cache_key($sub, $url = null)
    {
        return sprintf("%s-%s", $this->get_url_key($url), $sub);
    }

    /**
     * Return cache key for the url's status.
     * @param $url
     * @return string
     * @throws ECRedPressRedisParamsException
     */
    private function get_status_key($url = null)
    {
        return $this->get_cache_key('STATUS', $url);
    }

    /**
     * Return cache key for the url's headers.
     * @param null $url
     * @return string
     * @throws ECRedPressRedisParamsException
     */
    private function get_headers_key($url = null)
    {
        return $this->get_cache_key('HEADERS', $url);
    }

    /**
     * Return cache key for the url's page content.
     * @param null $url
     * @return string
     * @throws ECRedPressRedisParamsException
     */
    private function get_page_key($url = null)
    {
        return $this->get_cache_key('PAGE', $url);
    }

    /**
     * Should we cache based on the query string.
     * @return bool
     * @throws ECRedPressRedisParamsException
     */
    private function should_cache_query()
    {
        return $this->_config()['CACHE_QUERY'];
    }

    /**
     * Check if we should skip the cache for:
     * - NOCACHE GET var
     * - Cache Control max-age=0
     * @return bool
     * @throws ECRedPressRedisParamsException
     */
    public function should_skip_cache()
    {
        $nocacheSet = isset($_GET['NOCACHE']);
        ECRPLogger::get_logger()->engine->info("NOCACHE get var: " . $nocacheSet);
        $skip = ($nocacheSet or $this->is_comment_submission());
        ECRPLogger::get_logger()->engine->info("Comment: " . $skip);
        $skip = ($skip or $this->is_refresh());
        ECRPLogger::get_logger()->engine->info("Refresh: " . $skip);
        $skip = ($skip or !$this->is_cacheable_method());
        ECRPLogger::get_logger()->engine->info("Not cacheable method: " . $skip);
        $skip = ($skip or $this->is_logged_in());
        ECRPLogger::get_logger()->engine->info("Logged in: " . $skip);
        $skip = ($skip or defined('DONOTCACHEPAGE'));
        ECRPLogger::get_logger()->engine->info("DONOTCACHEPAGE set: " . $skip);
        $skip = ($skip or $this->is_cli());
        ECRPLogger::get_logger()->engine->info("Is CLI: " . $skip);

        return $skip;
    }

    /**
     * Check if we should delete the cache.
     * @return bool
     */
    private function should_delete_cache()
    {
        $queryDelete = ($this->is_logged_in() && (isset($_GET['ecrpd']) && $_GET['ecrpd'] == 'true'));
        $delete = $queryDelete or $this->is_comment_submission();
        return $delete;
    }

    /**
     * Check if the page cache exists.
     * @return int
     * @throws ECRedPressRedisParamsException
     */
    private function does_page_cache_exist()
    {
        return $this->client->exists($this->get_page_key());
    }

    /**
     * Set Ecrp-Cache header so we know what's going on with the cache.
     * @param $status
     * @param string $sub
     */
    private function set_ecrp_header($status, $sub = '')
    {
        if (headers_sent()) {
            return;
        }
        if ($sub !== '') {
            $sub = '-' . ucfirst($sub);
        }
        header("Ecrp-Cache$sub: $status");
    }

    /**
     * Get the cached version of the page.
     * @param null $url
     * @return string
     * @throws ECRedPressRedisParamsException
     */
    private function get_page_cache($url = null)
    {
        return $this->client->get($this->get_page_key($url));
    }

    /**
     * Get the cached version of the page's status.
     * @param null $url
     * @return string
     * @throws ECRedPressRedisParamsException
     */
    private function get_status_cache($url = null)
    {
        return $this->client->get($this->get_status_key($url));
    }

    /**
     * Get the cached page headers.
     * @param null $url
     * @return mixed
     * @throws ECRedPressRedisParamsException
     */
    private function get_headers_cache($url = null)
    {
        $headerJSON = $this->client->get($this->get_headers_key($url));
        return json_decode($headerJSON);
    }

    /**
     * Get all cached data for a url.
     * @param null $url
     * @return array
     * @throws ECRedPressRedisParamsException
     */
    private function get_cache($url = null)
    {
        return [
            'page' => $this->get_page_cache($url),
            'status' => $this->get_status_cache($url),
            'headers' => $this->get_headers_cache($url),
        ];
    }

    /**
     * Set the cache for a page.
     * @param $content
     * @param array $meta
     * @throws ECRedPressRedisParamsException
     */
    private function set_cache($content, $meta = [])
    {
        $ttl = $this->_config()['CACHE_EXPIRATION'];

        $ttl = $ttl === 0 ? null : $ttl;

        $resolution = $ttl ? 'ex' : null;

        $meta = array_merge([
            'status' => 200,
            'headers' => [],
            'url' => $this->_config()['CURRENT_URL'],
        ], $meta);

        ECRPLogger::get_logger()->engine->info("About to set cache for " . $meta['url']);

        $this->set_ecrp_header(date(DateTime::ISO8601), 'Generated');

        ECRPLogger::get_logger()->engine->warning("Cache exp settings: " . print_r([
//            $content,
                $resolution,
                $ttl,
            ], true));

        if ($resolution === null) {
            $this->client->set($this->get_page_key($meta['url']), $content);
            $this->client->set($this->get_status_key($meta['url']), $meta['status']);
            $this->client->set($this->get_headers_key($meta['url']), json_encode($meta['headers']));
        } else {
            $this->client->set($this->get_page_key($meta['url']), $content, $resolution, $ttl);
            $this->client->set($this->get_status_key($meta['url']), $meta['status'], $resolution, $ttl);
            $this->client->set($this->get_headers_key($meta['url']), json_encode($meta['headers']), $resolution, $ttl);
        }
    }

    /**
     * Delete the cache for a given url.
     * @param null $url
     * @throws ECRedPressRedisParamsException
     */
    public function delete_cache($url = null)
    {
        if (!$url) {
            $url = $this->_config()['CURRENT_URL'];
        }

        $url = str_replace("?ecrpd=true&", "?", $url);
        $url = str_replace("?ecrpd=true", "", $url);
        $url = str_replace("&ecrpd=true", "", $url);

        ECRPLogger::get_logger()->engine->info($url . " has cache? " . (!!$this->get_page_cache($url)));
        ECRPLogger::get_logger()->engine->info("About to delete cache: " . $url);
        $this->set_ecrp_header("Deleted", "Delete");
        $this->client->del([
            $this->get_page_key($url),
            $this->get_status_key($url),
            $this->get_headers_key($url),
        ]);
    }

    /**
     * Render the page for the current request from the cache.
     * @throws ECRedPressRedisParamsException
     */
    private function render_from_cache()
    {
        ECRPLogger::get_logger()->engine->info("About to fetch cache and render.");

        $cache = $this->get_cache();

        http_response_code($cache['status']);

        $headers = $cache['headers'];

        if (is_array($headers)) {
            foreach ($cache['headers'] as $header) {
                header($header);
            }
        }

        $this->set_ecrp_header('Hit');

        exit($cache['page']);
    }

    /**
     * Start the caching engine (basically begin output buffering).
     * @throws ECRedPressRedisParamsException
     */
    public function start_cache()
    {
        if (!$this->is_cache_enabled())
            return;

        if ($this->should_delete_cache())
            $this->delete_cache();

        if ($this->does_page_cache_exist() && !$this->should_skip_cache()) {
            try {
                $this->render_from_cache();
            } catch (Exception $e) {
                error_log($e->getMessage());
                ob_start();
            }
        } else {
            ob_start();
        }

    }

    /**
     * Complete output buffering and save the output to the cache.
     * @throws ECRedPressRedisParamsException
     */
    public function end_cache()
    {
        if (!$this->is_cache_enabled())
            return;

        $html = ob_get_contents();
        ob_end_clean();

        if (!$this->should_skip_cache())
            $this->set_cache($html, [
                'status' => http_response_code(),
                'headers' => headers_list(),
            ]);
        else
            $this->set_ecrp_header("Skipped");

        echo $html;
    }
}