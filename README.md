# Predis Adapter

## Intro
This is my custom [predis](https://github.com/predis/predis) wrapper for future work based on this work. It is a refactor of classes and tools I've developed and have used in many projects for years..


## Install
In order to work this need php8 and some classical php extensions plus a **local redis server** (for testing purpose) and **docker to test multiples instances of redis servers**.
```bash
apt install php8.1 php8.1-cli php8.1-common php8.1-mbstring php8.1-opcache
```

and perhaps other packages are required for composer to work smoothly
```bash
apt install php8.1-xml php8.1-http php8.1-dom
```


## Dev
install redis servers with docker
```bash
docker pull redis
docker-compose -f docker-compose.yml up -d
```


## TODOs
- merge both adaptee projects in on RedisAdapter
- improve CS fixer\
check on [my PHPRedis project](https://github.com/llegaz/PHPRedisAdpter) for even better performances !


## Contributing
You're welcome to propose things. I am open to criticism as long as it remains benevolent.



---
@see you space cowboy
---