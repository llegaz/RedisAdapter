<?php

declare(strict_types=1);

namespace LLegaz\Redis;

use LLegaz\Redis\Exception\ConnectionLostException;
use LogicException;
use Predis\Client;
use Predis\Response\Status;

/**
 * This class isn't really an adapter but it settles base for other projects based on it
 *
 * @todo refactor this (rename adapter to base maybe ? or Wrapper ?)
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisAdapter implements RedisInterface
{
    /**
     * current redis client in use
     *
     */
    private ?RedisClientInterface $client = null;

    /**
     * current redis client <b>context</b> in use
     *
     */
    private array $context = [];

    private ?string $lastErrorMsg = null;

    /**
     *
     * @param string $host
     * @param type $port
     * @param string $pwd
     * @param type $scheme
     * @param int $db
     */
    public function __construct(string $host = '127.0.0.1', int $port = 6379, ?string $pwd = null, string $scheme = 'tcp', int $db = 0, ?RedisClientInterface $client = null)
    {
        $this->context = [
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'database' => $db,
            // be ↓↓↓ careful ↓↓↓ with persistent connection
            // see https://github.com/predis/predis/issues/178#issuecomment-45851451
            /**
             * @todo try persistent connections with php-fpm
             */
            'persistent' => false,
        ];
        if ($pwd && strlen($pwd)) {
            $this->context['password'] = $pwd;
        }
        if ($client instanceof RedisClientInterface) {
            // for the sake of units
            $this->client = $client;
        } else {
            $this->client = RedisClientsPool::getClient($this->context);
            $this->context['client_id'] = $this->getRedisClientID();
            $this->checkIntegrity();
        }
    }

    /**
     * I know it's useless but I want it like this ! (maybe I'm Javascript ??!)
     */
    public function __destruct()
    {
        if ($this->client instanceof RedisClientInterface) {
            if (!$this->client->isPersistent()) {
                $this->client->disconnect();
            }
            unset($this->client);
        }
    }

    /**
     *
     * @param int $db
     * @return bool
     * @throws ConnectionLostException
     * @throws LogicException
     */
    public function selectDatabase(int $db): bool
    {
        if ($db < 0) {
            throw new LogicException('Databases are identified with unsigned integer');
        }
        if (!$this->isConnected()) {
            $this->throwCLEx();
        }
        $this->context['database'] = $db;
        $redisResponse = $this->client->select($db);

        return ($redisResponse instanceof Status && $redisResponse->getPayload() === 'OK') ? true : ($redisResponse === true);
    }

    /**
     *
     * @return array
     * @throws ConnectionLostException
     */
    public function clientList(): array
    {
        if (!$this->isConnected()) {
            $this->throwCLEx();
        }

        return $this->client->client('list');
    }

    /**
     *
     * @return bool
     * @throws ConnectionLostException
     */
    public function isConnected(): bool
    {
        $ping = false;

        try {
            $ping = $this->client->ping();
        } catch (\Exception $e) {
            $debug = '';
            if (defined('LLEGAZ_DEBUG')) {
                $debug = PHP_EOL . $e->getTraceAsString();
            }
            $this->lastErrorMsg = $e->getMessage() . $debug . PHP_EOL;
        } finally {
            if ($ping instanceof Status) {

                return ('PONG' === $ping->getPayload());
            }
            return $ping;
        }
    }

    /**
     * constructor helper
     */
    public static function createRedisAdapter(array $conf): self
    {
        $host = $conf['host'] ?? '127.0.0.1';
        $port = $conf['port'] ?? 6379;
        $pwd = $conf['password'] ?? null;
        $scheme = $conf['scheme'] ?? 'tcp';
        $db = $conf['database'] ?? 0;

        return new self($host, $port, $pwd, $scheme, $db);
    }

    /**
     * Check integrity between this adapter instance configuration context and
     * our stored singleton of redis client
     *
     * (see <b>PredisClientsPool</b> @class)
     *
     * @todo refactor this with pop_helper (units) to add function helper here in adapter class
     * (to pop out client_list['db'])
     *
     * @return bool
     * @throws ConnectionLostException
     * @throws LogicException
     */
    public function checkIntegrity(): bool
    {
        $context = $this->clientList();
        while (!isset($context['id']) || !$this->checkRedisClientId($context['id'])) {
            // we manage singletons of predis clients but they can have concurrent access to the same server
            $context = array_pop($context);
        }
        if (!$this->checkRedisClientId($context['id']) || !isset($context['db'])) {

            throw new LogicException('we\'ve got a problem here');
        }
        // check if database is well synced from upon instance context and predis singleton
        if ($this->context['database'] !== intval($context['db'])) {
            try {
                //dump('switch db from ' . $context['db'] .' to ' . $this->context['database']);
                return $this->selectDatabase($this->context['database']);
            } catch (\Exception $e) {
                $debug = '';
                if (defined('LLEGAZ_DEBUG')) {
                    $debug = PHP_EOL . $e->getTraceAsString();
                }
                $this->lastErrorMsg = $e->getMessage() . $debug . PHP_EOL;

                return false;
            }
        }

        return true;
    }

    /**
     * @throws ConnectionLostException
     * @return void
     */
    private function throwCLEx(): void
    {
        if ($this->lastErrorMsg && strlen($this->lastErrorMsg)) {
            throw new ConnectionLostException($this->lastErrorMsg);
        }
    }

    /**
     * check the client ID stored by remote Redis server
     */
    private function checkRedisClientId($mixed): bool
    {
        return (intval($mixed) === $this->context['client_id']);
    }

    /**
     * PHPUnit getter
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Predis client getter
     *
     * @return Client
     */
    public function getRedis(): Client
    {
        return $this->predis;
    }


    /**
     * PHPUnit DI setter
     *
     * @param Client $client predis client
     * @return PredisAdapter
     */
    public function setPredis(Client $client): self
    {
        $this->predis = $client;

        return $this;
    }

    /**
     * return a hash of the redis client used by this adapter
     *
     * @return string
     */
    public function getPredisClientID(): string
    {
        return spl_object_hash($this->predis);
    }

    /**
     *
     * @return int
     * @throws ConnectionLostException
     */
    public function getRedisClientID(): int
    {
        if (!$this->isConnected()) {
            $this->throwCLEx();
        }

        return intval($this->predis->client('id'));
    }
}
