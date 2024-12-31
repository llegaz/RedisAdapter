<?php

namespace LLegaz\Redis;

use Predis\Client;

/**
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class PredisClient extends Client implements RedisClientInterface {

    /**
     * @todo implement this
     * 
     * @return bool
     */
    public function isPersistent(): bool {
        return false;
    }

    public function __toString(): string {
        return "predis";
    }

}
