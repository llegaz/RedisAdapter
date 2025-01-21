# MEMO bordelique

src/
   |-> Cache     (psr-6 cache implementation + psr-16 cache entries)
   |-> Store     (pool functions, sets, unions, intersection)
   |-> Logger
   |-> Utils  (tools, splQueue for messages vs native redis Pub/Sub, examples... maybe useful implementation for cache entries like TokenBucket or the like)
   PredisAdapter


## dev workflow
Don't forget to test `composer test` with php-redis extension installed, but also without.. (predis fallback)
```bash
$ apt-cache policy php8.1-redis
```

## debug
```bash
$ telnet localhost 6379
> MONITOR
```

### default logfile in redis conf
```bash
tail -f /var/log/redis/redis-server.log
```
