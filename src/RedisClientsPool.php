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
    private static $clients = [];

    public function __destruct()
    {
        foreach (self::$clients as $client) {
            if ($client instanceof RedisClientInterface) {
                if (!$client->isPersistent()) {
                    $client->disconnect();
                }
                unset($client);
            }
        }
    }

    /**
     * Multiple clients handler
     *
     * @param array $conf
     * @return RedisClientInterface
     * @throws ConnectionLostException
     */
    public static function getClient(array $conf): Client
    {
        $arrKey = $conf;
        unset($arrKey['database']);
        $md5 = md5(serialize($arrKey));
        if (in_array($md5, array_keys(self::$clients))) {
            // get the client back
            $redis = self::$clients[$md5];
        } else {
            try {
                self::$clients[$md5] = [];
                if (isset($conf['persistent']) && $conf['persistent']) {
                    $conf['persistent'] = count(self::$clients) + 1;
                    $conf['persistent'] = (string) $conf['persistent'];
                }
                $redis = new Client($conf);
                // delayed connection
                //$redis->connect();
                self::$clients[$md5] = $redis;
            } catch (\Exception $e) {
                $debug = '';
                if (defined('LLEGAZ_DEBUG')) {
                    $debug = PHP_EOL . $e->getTraceAsString();
                }

                throw new ConnectionLostException('Connection to redis server is lost or not responding' . $debug . PHP_EOL, 500, $e);
            }
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
