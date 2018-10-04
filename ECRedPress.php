<?php
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/ECRedPressException.php";
require_once __DIR__ . "/ECRedPressLogger.php";

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
     * @var array $customConfig The configuration overrides provided by other code.
     */
    private $customConfig = [];
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
            $this->customConfig = $config;
        self::$instance = $this;
        $this->init();
    }

    /**
     * Return this and set config.
     * @param array $customConfig
     * @return $this
     */
    private function get_and_set_config($customConfig = null)
    {
        if ($customConfig !== null)
            $this->customConfig = $customConfig;
        return $this;
    }

    /**
     * Return the config object.
     * @return array
     * @throws ECRedPressRedisParamsException
     */
    public function public_config()
    {
        return $this->get_config();
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
            return self::$instance->get_and_set_config($customConfig);
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
     * Get array of comma-separated cacheable HTTP methods from ECRP_CACHEABLE_METHODS and return them as an array.
     * @return array
     */
    private function get_cacheable_methods_from_env()
    {
        $methods = getenv('ECRP_CACHEABLE_METHODS');
        if (!$methods)
            return [];
        else
            return array_map(function ($method) {
                return trim(strtoupper($method));
            }, explode(',', $methods));
    }

    /**
     * @param array $override
     * @return array
     * @throws ECRedPressRedisParamsException
     */
    private function get_config($override = [])
    {
        $redisUrl = getenv('ECRP_REDIS_URL');
        $cacheExp = getenv('ECRP_DEFAULT_EXPIRATION');
        $redis = $redisUrl ? parse_url($redisUrl) : [];
        $config = array_merge([
            'REDIS_HOST' => isset($redis['host']) ? $redis['host'] : null,
            'REDIS_PORT' => isset($redis['port']) ? $redis['port'] : null,
            'REDIS_PASSWORD' => isset($redis['pass']) ? $redis['pass'] : null,
            'CACHE_QUERY' => getenv('ECRP_CACHE_QUERY') == 'true',
            'CURRENT_URL' => $this->get_current_url(),
            'CACHEABLE_METHODS' => $this->get_cacheable_methods_from_env() ?: ['GET'],
            'CACHE_EXPIRATION' => $cacheExp ? (int)($cacheExp) : (60 * 60),
            'NOCACHE_REGEX' => [
                '.*\/wp-admin\/.*',
                '.*\/wp-login\.php$',
            ]
        ], $this->customConfig, $override);

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
            'host' => $this->get_config()['REDIS_HOST'],
            'port' => $this->get_config()['REDIS_PORT'],
        ];
        if (isset($this->get_config()['REDIS_PASSWORD']))
            $config['password'] = $this->get_config()['REDIS_PASSWORD'];

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
    private function is_cache_enabled()
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
     * Check if the request method is of a type we want to cache.
     * @return bool
     * @throws ECRedPressRedisParamsException
     */
    private function is_cacheable_method()
    {
        return in_array($_SERVER['REQUEST_METHOD'], $this->get_config()['CACHEABLE_METHODS']);
    }

    /**
     * Checks if this url should be cached.
     * @return bool
     * @throws ECRedPressRedisParamsException
     */
    public function is_cacheable_url()
    {
        $config = $this->get_config();
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
            $url = $this->get_config()['CURRENT_URL'];
        }
        $url = $this->should_cache_query() ? strtok($url, '?') : $url;
        return md5($url);
    }

    /**
     * Builds the current url.
     * @return string
     */
    private function get_current_url()
    {
        return sprintf(
            "%s://%s%s",
            $this->get_protocol(),
            $_SERVER['HTTP_HOST'],
            $_SERVER['REQUEST_URI']
        );
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
        return $this->get_config()['CACHE_QUERY'];
    }

    /**
     * Check if we should skip the cache for:
     * - NOCACHE GET var
     * - Cache Control max-age=0
     * @return bool
     * @throws ECRedPressRedisParamsException
     */
    private function should_skip_cache()
    {
        $nocacheSet = isset($_GET['NOCACHE']);
        ECRedPressLogger::get_logger()->engine->info("NOCACHE get var: " . $nocacheSet);
        $skip = ($nocacheSet or $this->is_comment_submission());
        ECRedPressLogger::get_logger()->engine->info("Comment: " . $skip);
        $skip = ($skip or !$this->is_cacheable_method());
        ECRedPressLogger::get_logger()->engine->info("Not cacheable method: " . $skip);
        $skip = ($skip or $this->is_logged_in());
        ECRedPressLogger::get_logger()->engine->info("Logged in: " . $skip);
        $skip = ($skip or defined('DONOTCACHEPAGE'));
        ECRedPressLogger::get_logger()->engine->info("DONOTCACHEPAGE set: " . $skip);
        $skip = ($skip or $this->is_cli());
        ECRedPressLogger::get_logger()->engine->info("Is CLI: " . $skip);

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
        $meta = array_merge([
            'status' => 200,
            'headers' => [],
            'url' => $this->get_config()['CURRENT_URL'],
        ], $meta);

        ECRedPressLogger::get_logger()->engine->info("About to set cache for " . $meta['url']);

        $this->client->set($this->get_page_key($meta['url']), $content);
        $this->client->set($this->get_status_key($meta['url']), $meta['status']);
        $this->client->set($this->get_headers_key($meta['url']), json_encode($meta['headers']));
    }

    /**
     * Delete the cache for a given url.
     * @param null $url
     * @throws ECRedPressRedisParamsException
     */
    public function delete_cache($url = null)
    {
        ECRedPressLogger::get_logger()->engine->info($url." has cache? ".(!!$this->get_page_cache($url)));
        ECRedPressLogger::get_logger()->engine->info("About to delete cache: ".$url);
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
        ECRedPressLogger::get_logger()->engine->info("About to fetch cache and render.");

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