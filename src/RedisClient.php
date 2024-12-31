<?php

namespace LLegaz\Redis;

/**
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisClient extends Redis implements RedisClientInterface {

    public function disconnect(): bool {
        $this->close();
    }

    /**
     * @todo implement this
     * 
     * @return bool
     */
    public function isPersistent(): bool {
        return false;
    }

    public function __toString(): string {
        return "redis";
    }

}