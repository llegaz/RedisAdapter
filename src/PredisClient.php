<?php

declare(strict_types=1);

namespace LLegaz\Redis;

use LLegaz\Redis\Exception\ConnectionLostException;
use Predis\Client;

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

    public function launchConnection(): void
    {
        throw new ConnectionLostException();
    }

    /**
     * @todo refacto here 
     *
     * @return bool
     */
    public function isPersistent(): bool
    {
        $c = $this->getConnection();
        if (!$c) {
            return false;
        }
        dump($c->getParameters()->toArray());
        $p = $c->getParameters()->toArray()['persistent'];

        /**
         * @todo this doesn't look good
         */
        return (is_string($p) && strlen($p));
    }

    public function toString(): string
    {
        return self::PREDIS;
    }
    
    /**
     * @todo check facade mset
     */
}
