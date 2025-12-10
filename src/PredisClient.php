<?php

declare(strict_types=1);

namespace LLegaz\Redis;

use LLegaz\Redis\Exception\ConnectionLostException;
use LLegaz\Redis\Exception\UnexpectedException;
use Predis\Client;
use Predis\Response\Status;

/**
 * @todo maybe we could / should unify returns system here with facade / adapter like mechanism ?
 *      (I need help with all those pattern I mix everything..)
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class PredisClient extends Client implements RedisClientInterface
{
    /**
     * lil hack for the RedisAdapter isConnected method
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return true;
    }

    /**
     * this should not be called in theory...
     *
     * @return void
     * @throws ConnectionLostException
     */
    public function launchConnection(): void
    {
        throw new ConnectionLostException();
    }

    /**
     *
     * @return bool
     */
    public function isPersistent(): bool
    {
        $c = $this->getConnection();
        $p = null;

        if ($c && $c->getParameters()) {
            $params = $c->getParameters()->toArray();
            if (isset($params['persistent'])) {
                $p = $params['persistent'];
            }
        }

        /**
         * persistent connection are given a name (like $p = 'persistent_2')
         * if the string is set then it is a named persistent connection within
         * Predis parameters
         */
        return (is_string($p) && strlen($p));
    }

    public function toString(): string
    {
        return self::PREDIS;
    }

    public function __toString(): string
    {
        return self::PREDIS;
    }


    /**
     * @todo maybe handle TTLs in another way to have a per key TTL basis.
     *       (and rework return part ?
     *
     * <code>redisResponse = redisResponse->getPayload() === 'OK';</code>
     *
     * in order to return redisResponse?)
     *
     * + handle TTL, expire returns (same applu for php-redis client version)
     *
     * @param array $data a key/value pairs array to store in redis
     * @param int $ttl  Time To Live for all the associated data
     * @return bool
     */
    public function multipleSet(array $data, int $ttl = null): bool
    {
        $redisResponse = false;
        $options = [
            'cas' => true, // Initialize with support for CAS operations
            'retry' => 3, // Number of retries on aborted transactions, after
                // which the client bails out with an exception.
        ];
        $this->transaction($options, function ($t) use ($data, $ttl, &$redisResponse) {
            $redisResponse = $t->mset($data);

            if (!is_null($ttl) && $ttl >= 0) {
                foreach ($data as $key => $value) {
                    $t->expire($key, $ttl);
                }
            }
        });

        if ($redisResponse instanceof Status) {
            return $redisResponse->getPayload() === 'OK';
        }

        throw new UnexpectedException();
    }

}
