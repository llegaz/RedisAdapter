# Redis Adapter

## Intro

This project isn't really an adapter, for me, it is a sort of <b>GATEWAY</b>.
(see [Martin Fowler, Gateway Pattern](https://martinfowler.com/articles/gateway-pattern.html)).

The goal here is to adapt use of either [Predis](https://github.com/predis/predis) client or native [PHP Redis](https://github.com/phpredis/phpredis/) client in a transparently way.
Those are the real adaptees, their respective classes are extended to adapt them to the gateway (`RedisAdapter`)
that will encapsulate one of them (as a redis client) and use one or the other indifferently depending on environment.

It will use preferably [PHP Redis](https://github.com/phpredis/phpredis/) if available (extension installed), or else fallback on [predis](https://github.com/predis/predis).


This class settles base for other projects based on it (PSR-6 Cache and so on)


## Install
```bash
composer require llegaz/redis-adapter
composer install
```
### env
In order to work we need php8 and some classical php extensions plus a **local redis server** (for testing purpose) and **docker to test multiples instances of redis servers**.
```bash
apt install php8.1 php8.1-cli php8.1-common php8.1-mbstring php8.1-opcache
```

and perhaps other packages may be required for composer to work smoothly
```bash
apt install php8.1-xml php8.1-http php8.1-dom
```


## Dev
install redis servers with docker (you will need a valid docker and docker-compose on your system)
```bash
docker pull redis
docker-compose -f docker-compose.yml up -d
```


## Contributing
You're welcome to propose things. I am open to criticism as long as it remains benevolent.


Stay tuned, by following me on github, for new features using [predis](https://github.com/predis/predis) and [PHP Redis](https://github.com/phpredis/phpredis/).

---
@see you space cowboy
---