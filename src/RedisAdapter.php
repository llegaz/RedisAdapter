<?php

declare(strict_types=1);

namespace LLegaz\Redis;

use LLegaz\Redis\Exception\ConnectionLostException;
use LLegaz\Redis\Exception\LocalIntegrityException;
use LLegaz\Redis\Exception\UnexpectedException;
use LogicException;
use Predis\Response\Status;

/**
 * This class isn't really an adapter, it is a <b>GATEWAY</b>.
 * (see <a href="https://martinfowler.com/articles/gateway-pattern.html">Martin Fowler, Gateway Pattern</a>).
 * @link https://martinfowler.com/articles/gateway-pattern.html
 *
 * The goal here is to adapt use of either Predis client or native PHP Redis client in a transparently way.
 * Those are the real adaptees, their respective classes are extended to adapt them for this class, the gateway
 * to encapsulate one of them and use one or the other indifferently depending on environment.
 *
 * It will use preferably PHP Redis if available (extension installed), or else fallback on predis.
 *
 *
 * This class settles base for other projects based on it (PSR-6 Cache and so on)
 *
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisAdapter
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
     * @param int $port
     * @param string|null $pwd
     * @param string $scheme
     * @param int $db
     * @param RedisClientInterface|null $client
     * @throws LocalIntegrityException
     */
    public function __construct(
        string $host = RedisClientInterface::DEFAULTS['host'],
        int $port = RedisClientInterface::DEFAULTS['port'],
        ?string $pwd = null,
        string $scheme = RedisClientInterface::DEFAULTS['scheme'],
        int $db = RedisClientInterface::DEFAULTS['database'],
        ?RedisClientInterface $client = null
    ) {
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
            RedisClientsPool::init();
            $this->client = RedisClientsPool::getClient($this->context);
            $this->context['client_id'] = $this->getRedisClientID();
            if (!$this->checkIntegrity()) {
                $this->throwLIEx();
            }

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
                unset($this->client);
            }
        }
    }

    /**
     *
     * @param int $db
     * @return bool
     * @throws ConnectionLostException
     * @throws LogicException
     * @throws UnexpectedException
     */
    public function selectDatabase(int $db): bool
    {
        if ($db < 0) {
            throw new LogicException('Databases are identified with unsigned integer');
        }
        if (!$this->isConnected()) {
            dump('selectDatabase excp on conn');
            $this->throwCLEx();
        }
        $this->context['database'] = $db;

        try {
            $redisResponse = $this->client->select($db);
        } catch (Exception $e) {
            $redisResponse = false;
            $this->formatException($e);
            dump('selectDatabase excp on slect');
            $this->throwUEx();
        } finally {
            return ($redisResponse instanceof Status && $redisResponse->getPayload() === 'OK') ? true : ($redisResponse === true);
        }
    }

    /**
     *
     * @return array
     * @throws ConnectionLostException
     * @throws UnexpectedException
     */
    public function clientList(): array
    {
        if (!$this->isConnected()) {
            dump('clientList excp on conn');
            $this->throwCLEx();
        }

        try {
            $list = $this->client->client('list');
        } catch (Exception $e) {
            dump('clientList excp on list');
            $this->formatException($e);
            $this->throwUEx();
        }

        return $list;
    }

    /**
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        $ping = false;

        try {
            // simulate predis delayed connection
            if (!$this->client->isConnected()) {
                $this->client->launchConnection();
            }
            $ping = $this->client->ping();
        } catch (\Exception $e) {
            $this->formatException($e);
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
        $host = $conf['host'] ?? RedisClientInterface::DEFAULTS['host'];
        $port = $conf['port'] ?? RedisClientInterface::DEFAULTS['port'];
        $pwd = $conf['password'] ?? null;
        $scheme = $conf['scheme'] ?? RedisClientInterface::DEFAULTS['scheme'];
        $db = $conf['database'] ?? RedisClientInterface::DEFAULTS['database'];

        return new self($host, $port, $pwd, $scheme, $db);
    }

    /**
     * Check integrity between this adapter instance configuration context and
     * our stored singleton of redis client
     *
     * (see <b>RedisClientsPool</b> @class)
     *
     * @return bool
     */
    public function checkIntegrity(): bool
    {
        // check if database is well synced from upon instance context and corresponding redis client singleton
        try {
            $context = $this->getClientCtxtFromRemote();
            if ($this->context['database'] !== intval($context['db'])) {
                dump('switch db from ' . $context['db'] . ' to ' . $this->context['database']);

                return $this->selectDatabase($this->context['database']);
            }
        } catch (\Exception $e) {
            $this->formatException($e);

            return false;
        }

        return true;
    }

    /**
     * return client context stored "remotely" on redis server
     * 
     * @return array
     * @throws LogicException
     */
    public function getClientCtxtFromRemote(): array
    {
        $context = $this->clientList();
        while (!isset($context['id']) || !$this->checkRedisClientId($context['id'])) {
            // we manage singletons of redis clients that can have concurrent access to the same server
            $context = array_pop($context);
        }
        if (!$this->checkRedisClientId($context['id']) || !isset($context['db'])) {

            throw new LogicException('we\'ve got a problem here');
        }

        return $context;
    }

    /**
     * @throws ConnectionLostException
     */
    private function throwCLEx(): void
    {
        if ($this->lastErrorMsg) {
            throw new ConnectionLostException($this->lastErrorMsg);
        }

        throw new ConnectionLostException();
    }

    /**
     * @throws LocalIntegrityException
     */
    private function throwLIEx(): void
    {
        if ($this->lastErrorMsg) {
            throw new LocalIntegrityException($this->lastErrorMsg);
        }

        throw new LocalIntegrityException();
    }

    /**
     * @throws UnexpectedException
     */
    private function throwUEx(): void
    {
        if ($this->lastErrorMsg) {
            throw new UnexpectedException($this->lastErrorMsg);
        }

        throw new UnexpectedException();
    }

    /**
     * 
     * @param \Throwable $t
     */
    private function formatException(\Throwable $t): void
    {
        $debug = '';
        if (defined('LLEGAZ_DEBUG')) {
            $debug = PHP_EOL . $t->getTraceAsString();
        }
        $this->lastErrorMsg = $t->getMessage() . $debug . PHP_EOL;
    }

    /**
     * check the client ID stored by remote Redis server
     *
     * @param mixed $mixed
     * @return bool
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
     * Redis client getter
     *
     * @return RedisClientInterface
     */
    public function getRedis(): RedisClientInterface
    {
        return $this->client;
    }


    /**
     * PHPUnit DI setter
     *
     * @param RedisClientInterface $client php-redis or predis client
     * @return RedisAdapter
     */
    public function setRedis(RedisClientInterface $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * return a hash of the redis client used by this adapter
     *
     * @return string
     */
    public function getID(): string
    {
        return spl_object_hash($this->client);
    }

    /**
     * fetch id managed by remote Redis server for the ongoing connection
     * (and thus client used here).
     *
     * @return int
     * @throws ConnectionLostException
     */
    public function getRedisClientID(): int
    {
        if (!$this->isConnected()) {
            $this->throwCLEx();
        }

        return intval($this->client->client('id'));
    }
}
