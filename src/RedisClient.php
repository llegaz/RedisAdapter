<?php

declare(strict_types=1);

namespace LLegaz\Redis;

/**
 * PHP Redis Adapter
 *
 * @todo maybe we could / should unify returns system here with facade / adapter like mechanism ?
 *      (I need help with all those pattern I mix everything..)
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisClient extends \Redis implements RedisClientInterface
{
    private bool $isConnected = false;
    private bool $isPersistent = false;
    private ?string $persistent = null;
    private string $con = '';
    private ?string $pwd = null;
    private int $port;

    public function __construct(array $conf)
    {
        if (isset($conf['persistent']) && $conf['persistent'] && strlen($conf['persistent'])) {
            $this->isPersistent = true;
            $this->persistent = $conf['persistent'];
        }
        if (isset($conf['scheme']) && strlen($conf['scheme'])) {
            $this->con .= $conf['scheme'];
        } else {
            $this->con .= self::DEFAULTS['scheme'];
        }
        $this->con .= '://';
        if (!isset($conf['host']) || !strlen($conf['host']) || !isset($conf['port']) || ($this->port = intval($conf['port'])) < 0) {
            throw new LogicException('Host and port should be set properly');
        }
        $this->con .= $conf['host'];
        if (isset($conf['password'])) {
            $this->pwd = $conf['password'];
        }
    }

    public function disconnect(): void
    {
        $this->close();
    }

    public function launchConnection(): bool
    {
        if ($this->isPersistent) {
            $this->isConnected = parent::pconnect($this->con, $this->port, self::TIMEOUT, $this->persistent);
        } else {
            $this->isConnected = parent::connect($this->con, $this->port, self::TIMEOUT);
        }

        if ($this->pwd) {
            try {
                $this->isConnected = parent::auth($this->pwd);
            } catch (\Throwable $t) {
                $this->isConnected = false;
            }
        }

        return $this->isConnected;
    }

    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    public function isPersistent(): bool
    {
        return $this->isPersistent;
    }

    public function toString(): string
    {
        return self::PHP_REDIS;
    }

    public function __toString(): string
    {
        return self::PHP_REDIS;
    }

    /**
     * @todo check facade mset
     */
}
