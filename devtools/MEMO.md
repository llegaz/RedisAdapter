
src/
   |-> Cache     (psr-6 cache implementation + psr-16 cache entries)
   |-> Store     (pool functions, sets, unions, intersection)
   |-> Logger
   |-> Utils  (tools, splQueue for messages vs native redis Pub/Sub, examples... maybe useful implementation for cache entries like TokenBucket or the like)
   PredisAdapter


```bash
$ telnet localhost 6379
> MONITOR
```

- Note: tcp mode seems to rely heavily on `predis/src/Connection/StreamConnection.php`

