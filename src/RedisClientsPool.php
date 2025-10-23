<?php

declare(strict_types=1);

namespace LLegaz\Redis;

use LLegaz\Redis\Exception\ConnectionLostException;

/**
 * This class is used to manage all redis clients for the RedisAdapter class
 * thus ensuring no duplication of resources
 *
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisClientsPool
{
    /**
     * store all redis clients (multiple clients)
     *
     * @var map
     */
    private static array $clients = [];

    /**
     * aims to prevent unneeded integrity checks
     * (in case of unique redis connection client used in a sole concrete class)
     *
     * @var map
     */
    private static array $oracle = [];

    /**
     *
     * @var bool
     */
    private static bool $isRedis;

    /**
     *
     * @var bool
     */
    private static bool $init = false;

    public static function init(): void
    {
        if (!self::$init) {
            register_shutdown_function('self::destruct');
            self::$isRedis = in_array('redis', get_loaded_extensions());
            self::$init = true;
        }
    }

    public static function destruct(): void
    {
        do {
            $client = array_pop(self::$clients);
            if ($client instanceof RedisClientInterface) {
                if (!$client->isPersistent()) {
                    $client->disconnect();
                }
                unset($client);
            }
        } while (count(self::$clients));
    }

    /**
     * Multiple clients handler -
     * it returns and manage singletons (predis or phpredis client) for a client/server pair
     *
     * @param array $conf
     * @return RedisClientInterface
     * @throws ConnectionLostException
     */
    public static function getClient(array $conf): RedisClientInterface
    {
        $md5 = self::getMDHash($conf);
        if (in_array($md5, array_keys(self::$clients))) {
            // get the client back
            $redis = self::$clients[$md5];
        } else {
            if (isset($conf['persistent']) && $conf['persistent']) {
                $conf['persistent'] = count(self::$clients) + 1;
                $conf['persistent'] = (string) $conf['persistent'];
            }

            /***
             * CRITICAL: We use include_once instead of autoload to prevent fatal errors.
             *
             * RedisClient extends \Redis, which only exists when the redis extension
             * is loaded. If PHP's parser tries to load the class definition when the
             * extension is NOT loaded, it will throw a FATAL ERROR.
             *
             * By using include_once conditionally, we ensure the class is only parsed
             * when we know \Redis exists.
             *
             * Alternative considered: composition instead of inheritance, but this
             * would lose type safety and add overhead via __call().
             *
             * Also, note that we use a strategy pattern here and a fall back on PredisClient
             * if php-redis extension isn't available.
             */
            if (self::$isRedis) {
                include_once 'RedisClient.php';
                $redis = new RedisClient($conf);
            } else {
                $redis = new PredisClient($conf);
            }

            // delayed connection
            //$redis->connect();
            self::$clients[$md5] = $redis;
        }

        if ($redis instanceof RedisClientInterface) {
            if (!isset(self::$oracle[$md5])) {
                self::$oracle[$md5] = 0;
            }
            self::$oracle[$md5]++;

            return $redis;
        }

        throw new ConnectionLostException('Predis client was not instanciated correctly' . PHP_EOL, 500);
    }

    /**
     * the oracle is keeping count of the number of time a client has been accessed
     * if it is accessed only once the concrete class using it can be assured she is
     * the only one changing its context (primarily used to sync the right db)
     *
     * @param array $conf
     * @return int
     */
    public static function getOracle(array $conf): int
    {
        $md5 = self::getMDHash($conf);

        return self::$oracle[$md5]; // should be set (always ?)
    }

    /**
     * Units purpose
     *
     * @param array $conf
     * @return void
     */
    public static function setOracle(array $conf): void
    {
        $md5 = self::getMDHash($conf);

        self::$oracle[$md5] = 9; // hack again for units' sake :/
    }

    private static function getMDHash(array $conf): string
    {
        $arrKey = $conf;
        unset($arrKey['database']);
        unset($arrKey['client_id']);

        return md5(serialize($arrKey));
    }

    public static function clientCount(): int
    {
        return count(self::$clients);
    }
}
