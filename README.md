# ECRedPress

Dead simple HTTP Redis Cache for WordPress inspired by work from Mark Hilton, Jeedo Aquino, and Jim Westergren. 

_NOTE_: This is something we're testing out, because we didn't enjoy the experience of trying to setup W3 Total Cache with Redis on Heroku. It didn't suit our needs, so we're testing out this idea. Be warned that this project is far from production ready, but feel free to give it a shot and let us know how it goes for you.

Check out their work:
https://github.com/markhilton/redis-http-cache
https://gist.github.com/JimWestergren/3053250#file-index-with-redis-php
http://www.jeedo.net/lightning-fast-wordpress-with-nginx-redis/

## Testing



## Installation

As of 2018-08-09, you'll need to install by adding the contents of this project to your plugins directory in a folder called ECRedPress. Then you'll want to setup the `ECRP_REDIS_URL` environment variable. This should be a url in the format `redis://<user>:<password>@<host>:<port>` where `<user>` can just be a single letter, because there isn't a user. This is the format provided by [Heroku](https://devcenter.heroku.com/articles/heroku-redis) which is the environment we're building this for originally. Then you'll want to wrap the contents of your WordPress `index.php` in calls to `ECRedPress::get_ecrp()->start()` and `ECRedPress::get_ecrp()->stop()` calls.

## CONFIGURATION

### Environment Variables

- `ECRP_REDIS_URL`: The connection string. As defined above. See [Heroku](https://devcenter.heroku.com/articles/heroku-redis)'s Redis connection string.
- `ECRP_DEFAULT_EXPIRATION`: How long until the cached content expires. Defaults to 1 hour.
- `ECRP_CACHEABLE_METHODS`: Comma separated HTTP methods that should be cached. For example, `GET,POST`.

### ECRP::customConfig

If you want to tweak things in code, you'll need to interact with the engine. The only way to access the caching engine is to load it using `ECRedPress::get_ecrp`. If the engine has already been initialized, you will get the existing instance, otherwise it will be initialized and then returned. If you are loading the engine for the first time (usually in the WP index.php file), you can pass an array with custom configuration options, including options for the Redis client which will override configuration from Environment Variables. Once the engine is initialized, you will not be able to change the Redis client's configuration, but you will be able to change any other configuration options by passing a custom config array to `ECRedPress::get_ecrp`. The full custom config options are listed below:

- `REDIS_HOST` 
    - *String*
    - The Redis instance's host. No support for clusters yet. Only available the first time `get_ecrp` is called.
- `REDIS_PORT` 
    - *String*
    - The Redis instance's port. Only available the first time `get_ecrp` is called.
- `REDIS_PASSWORD`
    - *String*
    - Only available the first time `get_ecrp` is called.
- `CACHE_QUERY` 
    - *Bool*
    - Determines whether or not to cache urls with different query strings separately. Default is false.
- `CURRENT_URL` 
    - *String*
    - By default, this is loaded from the request. If, however, you need to interact with the engine as if it were loaded from a url that it isn't actually loaded from, then you can use this to tell the engine which url it should "pretend" to work from.
- `CACHEABLE_METHODS` 
    - *String Array*
    - An array of string with HTTP methods, like GET or POST, which should be cached. Default is just GET.
- `CACHE_EXPIRATION` 
    - *Int*
    - An integer specifying how long a page should remain in the cache. Default is 15 minutes.
- `NOCACHE_REGEX` 
    - *String Array*
    - An array with regular expressions applied to urls that should _not_ be cached.
 