# Predis Adapter

## Intro
This is my custom [predis](https://github.com/predis/predis) wrapper for future work based on this work. It is a refactor of classes and tools I've developed and have used in many projects for years..


## Install
```bash
composer require llegaz/redis-adapter
composer install
```
### env
In order to work this need php8 and some classical php extensions plus a **local redis server** (for testing purpose) and **docker to test multiples instances of redis servers**.
```bash
apt install php8.1 php8.1-cli php8.1-common php8.1-mbstring php8.1-opcache
```

and perhaps other packages are required for composer to work smoothly
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