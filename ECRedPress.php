<?php
require_once __DIR__."/vendor/autoload.php";
require_once __DIR__."/ECRedPressException.php";
require_once __DIR__."/ECRedPressLogger.php";

/**
 * Class ECRedPress
 * @author Raphael Titsworth-Morin
 * @see https://github.com/markhilton/redis-http-cache
 * @see https://gist.github.com/JimWestergren/3053250#file-index-with-redis-php
 * @see http://www.jeedo.net/lightning-fast-wordpress-with-nginx-redis/
 *
 * Re-structured from code and ideas from Jim Westergren, Jeedo Aquino, and Mark Hilton.
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
    private function getAndSetConfig($customConfig = null)
    {
        if ($customConfig !== null)
            $this->customConfig = $customConfig;
        return $this;
    }

    /**
     * Gets the current EC RedPress instance or creates one.
     * @param array $customConfig
     * @return ECRedPress
     * @throws ECRedPressRedisParamsException
     */
    public static function getEcrp($customConfig = null)
    {
        if (self::$instance != null) {
            return self::$instance->getAndSetConfig($customConfig);
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
        $this->initClient();
    }

    /**
     * Get array of comma-separated cacheable HTTP methods from ECRP_CACHEABLE_METHODS and return them as an array.
     * @return array
     */
    private function getCacheableMethodsFromEnv()
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
    private function getConfig($override = [])
    {
        $redisUrl = getenv('ECRP_REDIS_URL');
        $cacheExp = getenv('ECRP_DEFAULT_EXPIRATION');
        $redis = $redisUrl ? parse_url($redisUrl) : [];
        $config = array_merge([
            'REDIS_HOST' => isset($redis['host']) ? $redis['host'] : null,
            'REDIS_PORT' => isset($redis['port']) ? $redis['port'] : null,
            'REDIS_PASSWORD' => isset($redis['pass']) ? $redis['pass'] : null,
            'CACHE_QUERY' => getenv('ECRP_CACHE_QUERY') == 'true',
            'CURRENT_URL' => $this->getCurrentUrl(),
            'CACHEABLE_METHODS' => $this->getCacheableMethodsFromEnv() ?: ['GET'],
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
    private function initClient()
    {
        $config = [
            'host' => $this->getConfig()['REDIS_HOST'],
            'port' => $this->getConfig()['REDIS_PORT'],
        ];
        if (isset($this->getConfig()['REDIS_PASSWORD']))
            $config['password'] = $this->getConfig()['REDIS_PASSWORD'];

        $this->client = new Predis\Client($config);
    }

    /**
     * Check if we are logged in.
     * @return bool
     */
    private function isLoggedIn()
    {
        return !!preg_match("/wordpress_logged_in/", var_export($_COOKIE, true));
    }

    /**
     * Check if we're running from the CLI in which case, bypass the cache.
     * @return bool
     */
    private function isCli()
    {
        return defined('WP_CLI');
    }

    /**
     * Is the cache enabled.
     * @return array|false|string
     */
    private function isCacheEnabled()
    {
        return !!getenv('ECRP_ENABLED');
    }

    /**
     * Check if request is a comment submission.
     * @return bool
     */
    private function isCommentSubmission()
    {
        return (isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'max-age=0');
    }

    /**
     * Check if the request method is of a type we want to cache.
     * @return bool
     * @throws ECRedPressRedisParamsException
     */
    private function isCacheableMethod()
    {
        return in_array($_SERVER['REQUEST_METHOD'], $this->getConfig()['CACHEABLE_METHODS']);
    }

    /**
     * Checks if this url should be cached.
     * @return bool
     * @throws ECRedPressRedisParamsException
     */
    public function isCacheableUrl()
    {
        $config = $this->getConfig();
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
    private function getUrlKey($url = null)
    {
        if (!$url) {
            $url = $this->getConfig()['CURRENT_URL'];
        }
        $url = $this->shouldCacheQuery() ? strtok($url, '?') : $url;
        return md5($url);
    }

    /**
     * Builds the current url.
     * @return string
     */
    private function getCurrentUrl()
    {
        return sprintf(
            "%s://%s%s",
            $this->getProtocol(),
            $_SERVER['HTTP_HOST'],
            $_SERVER['REQUEST_URI']
        );
    }

    /**
     * Returns the protocol, either through X_FORWARDED header if set, or HTTPS.
     * @return string
     */
    private function getProtocol()
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
    private function getCacheKey($sub, $url = null)
    {
        return sprintf("%s-%s", $this->getUrlKey($url), $sub);
    }

    /**
     * Return cache key for the url's status.
     * @param $url
     * @return string
     * @throws ECRedPressRedisParamsException
     */
    private function getStatusKey($url = null)
    {
        return $this->getCacheKey('STATUS', $url);
    }

    /**
     * Return cache key for the url's headers.
     * @param null $url
     * @return string
     * @throws ECRedPressRedisParamsException
     */
    private function getHeadersKey($url = null)
    {
        return $this->getCacheKey('HEADERS', $url);
    }

    /**
     * Return cache key for the url's page content.
     * @param null $url
     * @return string
     * @throws ECRedPressRedisParamsException
     */
    private function getPageKey($url = null)
    {
        return $this->getCacheKey('PAGE', $url);
    }

    /**
     * Should we cache based on the query string.
     * @return bool
     * @throws ECRedPressRedisParamsException
     */
    private function shouldCacheQuery()
    {
        return $this->getConfig()['CACHE_QUERY'];
    }

    /**
     * Check if we should skip the cache for:
     * - NOCACHE GET var
     * - Cache Control max-age=0
     * @return bool
     * @throws ECRedPressRedisParamsException
     */
    private function shouldSkipCache()
    {
        $nocacheSet = isset($_GET['NOCACHE']);
        $skip = ($nocacheSet or $this->isCommentSubmission());
        $skip = ($skip or !$this->isCacheableMethod());
        $skip = ($skip or $this->isLoggedIn());
        $skip = ($skip or defined('DONOTCACHEPAGE'));
        $skip = ($skip or $this->isCli());

        return $skip;
    }

    /**
     * Check if we should delete the cache.
     * @return bool
     */
    private function shouldDeleteCache()
    {
        $queryDelete = ($this->isLoggedIn() && (isset($_GET['ecrpd']) && $_GET['ecrpd'] == 'true'));
        $delete = $queryDelete or $this->isCommentSubmission();
        return $delete;
    }

    /**
     * Check if the page cache exists.
     * @return int
     * @throws ECRedPressRedisParamsException
     */
    private function doesPageCacheExist()
    {
        return $this->client->exists($this->getPageKey());
    }

    /**
     * Set Ecrp-Cache header so we know what's going on with the cache.
     * @param $status
     * @param string $sub
     */
    private function setEcrpHeader($status, $sub='')
    {
        if($sub !== ''){
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
    private function getPageCache($url = null)
    {
        return $this->client->get($this->getPageKey($url));
    }

    /**
     * Get the cached version of the page's status.
     * @param null $url
     * @return string
     * @throws ECRedPressRedisParamsException
     */
    private function getStatusCache($url = null)
    {
        return $this->client->get($this->getStatusKey($url));
    }

    /**
     * Get the cached page headers.
     * @param null $url
     * @return mixed
     * @throws ECRedPressRedisParamsException
     */
    private function getHeadersCache($url = null)
    {
        $headerJSON = $this->client->get($this->getHeadersKey($url));
        return json_decode($headerJSON);
    }

    /**
     * Get all cached data for a url.
     * @param null $url
     * @return array
     * @throws ECRedPressRedisParamsException
     */
    private function getCache($url = null)
    {
        return [
            'page' => $this->getPageCache($url),
            'status' => $this->getStatusCache($url),
            'headers' => $this->getHeadersCache($url),
        ];
    }

    /**
     * Set the cache for a page.
     * @param $content
     * @param array $meta
     * @throws ECRedPressRedisParamsException
     */
    private function setCache($content, $meta = [])
    {
        $meta = array_merge([
            'status' => 200,
            'headers' => [],
            'url' => $this->getConfig()['CURRENT_URL'],
        ], $meta);

        ECRedPressLogger::getLogger()->engine->info("About to set cache for ".$meta['url']);

        $this->client->set($this->getPageKey($meta['url']), $content);
        $this->client->set($this->getStatusKey($meta['url']), $meta['status']);
        $this->client->set($this->getHeadersKey($meta['url']), json_encode($meta['headers']));
    }

    /**
     * Delete the cache for a given url.
     * @param null $url
     * @throws ECRedPressRedisParamsException
     */
    public function deleteCache($url = null)
    {
        ECRedPressLogger::getLogger()->engine->info("About to delete cache.");
        $this->setEcrpHeader("Deleted", "Delete");
        $this->client->del([
            $this->getPageKey($url),
            $this->getStatusKey($url),
            $this->getHeadersKey($url),
        ]);
    }

    /**
     * Render the page for the current request from the cache.
     * @throws ECRedPressRedisParamsException
     */
    private function renderFromCache()
    {
        ECRedPressLogger::getLogger()->engine->info("About to fetch cache and render.");

        $cache = $this->getCache();

        http_response_code($cache['status']);

        $headers = $cache['headers'];

        if (is_array($headers)) {
            foreach ($cache['headers'] as $header) {
                header($header);
            }
        }

        $this->setEcrpHeader('Hit');

        exit($cache['page']);
    }

    /**
     * Start the caching engine (basically begin output buffering).
     * @throws ECRedPressRedisParamsException
     */
    public function startCache()
    {
        if(!$this->isCacheEnabled())
            return;

        if ($this->shouldDeleteCache())
            $this->deleteCache();

        if ($this->doesPageCacheExist() && !$this->shouldSkipCache()) {
            try {
                $this->renderFromCache();
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
    public function endCache()
    {
        if(!$this->isCacheEnabled())
            return;

        $html = ob_get_contents();
        ob_end_clean();

        if (!$this->shouldSkipCache())
            $this->setCache($html, [
                'status' => http_response_code(),
                'headers' => headers_list(),
            ]);
        else {
            $this->setEcrpHeader("Skipped");
        }

        echo $html;
    }
}