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
     * @todo check facade mset
     */
}
