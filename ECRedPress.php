<?php
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
require_once "vendor/autoload.php";

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

    private function __construct($config)
    {
        if($config)
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
        if($customConfig !== null)
            $this->customConfig = $customConfig;
        return $this;
    }

    /**
     * Gets the current EC RedPress instance or creates one.
     * @param array $customConfig
     * @return ECRedPress
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
     * @param array $customConfig
     */
    private function init()
    {
        $this->initClient();
    }

    private function getConfig($override = [])
    {
        $redis = parse_url(getenv('ECRP_REDIS_URL'));
        return array_merge([
            'REDIS_HOST' => $redis['host'],
            'REDIS_PORT' => $redis['port'],
            'REDIS_PASSWORD' => $redis['pass'],
            'CACHE_QUERY' => getenv('ECRP_CACHE_QUERY') ?: 'false',
            'CURRENT_URL' => $this->getCurrentUrl(),
            'CACHEABLE_METHODS' => ['GET'],
            'CACHE_EXPIRATION' => 60 * 60,
            'NOCACHE_REGEX' => [
                '.*\/wp-admin\/.*',
                '.*\/wp-login\.php$',
            ]
        ], $this->customConfig, $override);
    }

    /**
     * Initialize Predis client.
     */
    private function initClient()
    {
        $this->client = new Predis\Client([
            'host' => $this->getConfig()['REDIS_HOST'],
            'port' => $this->getConfig()['REDIS_PORT'],
            'password' => $this->getConfig()['REDIS_PASSWORD'],
        ]);
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
     */
    private function isCacheableMethod()
    {
        return in_array($_SERVER['REQUEST_METHOD'], $this->getConfig()['CACHEABLE_METHODS']);
    }

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
     */
    private function getCacheKey($sub, $url = null)
    {
        return sprintf("%s-%s", $this->getUrlKey($url), $sub);
    }

    /**
     * Return cache key for the url's status
     * @param $url
     * @return string
     */
    private function getStatusKey($url = null)
    {
        return $this->getCacheKey('STATUS', $url);
    }

    /**
     * Return cache key for the url's headers
     * @param null $url
     * @return string
     */
    private function getHeadersKey($url = null)
    {
        return $this->getCacheKey('HEADERS', $url);
    }

    /**
     * Return cache key for the url's page content
     * @param null $url
     * @return string
     */
    private function getPageKey($url = null)
    {
        return $this->getCacheKey('PAGE');
    }

    /**
     * Should we cache based on the query string.
     * @return bool
     */
    private function shouldCacheQuery()
    {
        return $this->getConfig()['CACHE_QUERY'] == 'true';
    }

    /**
     * Check if we should skip the cache for:
     * - NOCACHE GET var
     * - Cache Control max-age=0
     * @return bool
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
     */
    private function doesPageCacheExist()
    {
        return $this->client->exists($this->getPageKey());
    }

    /**
     * Get the cached version of the page.
     * @param null $url
     * @return string
     */
    private function getPageCache($url = null)
    {
        return $this->client->get($this->getPageKey($url));
    }

    /**
     * Get the cached version of the page's status.
     * @param null $url
     * @return string
     */
    private function getStatusCache($url = null)
    {
        return $this->client->get($this->getStatusKey($url));
    }

    /**
     * Get the cached page headers.
     * @param null $url
     * @return mixed
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
     */
    private function setCache($content, $meta = [])
    {
        $meta = array_merge([
            'status' => 200,
            'headers' => [],
            'url' => $this->getConfig()['CURRENT_URL'],
        ], $meta);
        $this->client->set($this->getPageKey($meta['url']), $content);
        $this->client->set($this->getStatusKey($meta['url']), $meta['status']);
        $this->client->set($this->getHeadersKey($meta['url']), $meta['headers']);
    }

    /**
     * Delete the cache for a given url.
     * @param null $url
     */
    public function deleteCache($url = null)
    {
        $this->client->del([
            $this->getPageKey($url),
            $this->getStatusKey($url),
            $this->getHeadersKey($url),
        ]);
    }

    /**
     * Render the page for the current request from the cache.
     */
    private function renderFromCache()
    {
        $cache = $this->getCache();

        http_response_code($cache['status']);

        $headers = $cache['headers'];

        error_log(print_r($headers, true));

        if (is_array($headers)) {
            foreach ($cache['headers'] as $header) {
                header($header);
            }
        }

        header('Cache: HIT');
        header('ECRP-Cache: Active');

        exit($cache['page']);
    }

    /**
     * Start the caching engine (basically begin output buffering).
     */
    public function startCache()
    {
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
     */
    public function endCache()
    {
        $html = ob_get_contents();
        ob_end_clean();

        if (!$this->shouldSkipCache())
            $this->setCache($html, [
                'status' => http_response_code(),
                'headers' => headers_list(),
            ]);

        echo $html;
    }
}