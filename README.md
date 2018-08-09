# ECRedPress

Dead simple HTTP Redis Cache for WP based on work from Mark Hilton, Jeedo Aquino, and Jim Westergren. 

_NOTE_: This is something we're testing out, because we found that setting up W3 Total Cache with Redis was overly complicated, and didn't suit our needs, but we're still just testing out the idea, so be warned that this project is far from production ready.

Check out their work:
https://github.com/markhilton/redis-http-cache
https://gist.github.com/JimWestergren/3053250#file-index-with-redis-php
http://www.jeedo.net/lightning-fast-wordpress-with-nginx-redis/

## Installation

As of 2018-08-09, you'll need to install by adding the contents of this project to your plugins directory in a folder called ECRedPress. Then you'll want to setup the `ECRP_REDIS_URL` environment variable. This should be a url in the format `redis://<user>:<password>@<host>:<port>` where `<user>` can just be a single letter, because there isn't a user. This is the format provided by [Heroku](https://devcenter.heroku.com/articles/heroku-redis) which is the environment we're building this for originally. Then you'll want to wrap the contents of your WordPress `index.php` in calls to `ECRedPress::getEcrp()->start()` and `ECRedPress::getEcrp()->stop()` calls.

## CONFIGURATION

### Environment Variables

- `ECRP_REDIS_URL`: The connection string. As defined above. See [Heroku](https://devcenter.heroku.com/articles/heroku-redis)'s Redis connection string.
- `ECRP_CACHE_QUERY`: Whether or not we should take query strings into account when determining what to cache. Defaults to false. For example, if set to true, would cache `https://example.com?foo=bar` separately from `https://example.com?foo=baz`.
- `ECRP_DEFAULT_EXPIRATION`: How long until the cached content expires. Defaults to 1 hour.

### ECRP::customConfig

The ECRP instance should always be retrieved using `ECRedPress::getEcrp`.