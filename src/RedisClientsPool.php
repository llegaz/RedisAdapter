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
        $arrKey = $conf;
        unset($arrKey['database']);
        $md5 = md5(serialize($arrKey));
        if (in_array($md5, array_keys(self::$clients))) {
            // get the client back
            $redis = self::$clients[$md5];
        } else {
            if (isset($conf['persistent']) && $conf['persistent']) {
                $conf['persistent'] = count(self::$clients) + 1;
                $conf['persistent'] = (string) $conf['persistent'];
            }

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

            return $redis;
        }

        throw new ConnectionLostException('Predis client was not instanciated correctly' . PHP_EOL, 500);
    }

    public static function clientCount(): int
    {
        return count(self::$clients);
    }
}
