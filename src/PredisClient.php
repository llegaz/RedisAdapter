<?php

declare(strict_types=1);

namespace LLegaz\Redis;

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

    /**
     * @todo implement this
     *
     * @return bool
     */
    public function isPersistent(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return 'predis';
    }

}
