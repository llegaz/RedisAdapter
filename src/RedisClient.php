<?php

declare(strict_types=1);

namespace LLegaz\Redis;

/**
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisClient extends Redis implements RedisClientInterface
{
    public function disconnect(): void
    {
        $this->close();
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
        return self::PHP_REDIS;
    }

}
