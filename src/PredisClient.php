<?php

declare(strict_types=1);

namespace LLegaz\Redis;

use LLegaz\Redis\Exception\ConnectionLostException;
use Predis\Client;

/**
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
        if (!$c) {
            return false;
        }
        $p = $c->getParameters()->toArray()['persistent'];

        return (is_string($p) && strlen($p));
    }

    public function toString(): string
    {
        return self::PREDIS;
    }
}
