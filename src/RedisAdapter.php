<?php

declare(strict_types=1);

namespace LLegaz\Redis;

use LLegaz\Redis\Exception\ConnectionLostException;
use LLegaz\Redis\Exception\LocalIntegrityException;
use LLegaz\Redis\Exception\UnexpectedException;
use LogicException;
use Predis\Response\Status;
use Throwable;

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

    private ?float $lastPing = null;

    /**
     *
     * @todo add a logger interface to the class, see to format/log exceptions externally of this class
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
        bool $persistent = false,
        ?RedisClientInterface $client = null
    ) {
        $this->context = [
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'database' => $db,
            /**
             * be ↓↓↓ careful ↓↓↓ with persistent connection
             * see https://github.com/predis/predis/issues/178#issuecomment-45851451
             */
            'persistent' => $persistent,
        ];
        if ($pwd && strlen($pwd)) {
            $this->context['password'] = $pwd;
        }
        if ($client instanceof RedisClientInterface) {
            // for the sake of units
            $this->client = $client;
            $this->context['client_id'] = 1337;
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
            $this->throwCLEx();
        }

        try {
            $redisResponse = $this->client->select($db);
            $this->context['database'] = $db;
        } catch (Throwable $e) {
            $redisResponse = null;
            $this->formatException($e);
        } finally {
            if (is_null($redisResponse)) {
                $this->throwUEx();
            } else {

                return ($redisResponse instanceof Status && $redisResponse->getPayload() === 'OK') ? true : ($redisResponse === true);
            }
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
            $this->throwCLEx();
        }

        try {
            $list = $this->client->client('list');
        } catch (Throwable $e) {
            $this->formatException($e);
            $this->throwUEx();
        }

        return $list;
    }

    /**
     *
     * @return bool
     * @throws ConnectionLostException
     */
    public function isConnected(): bool
    {
        $ping = false;

        // simulate predis delayed connection
        if (!$this->client->isConnected() && !$this->client->launchConnection()) {
            return false;
        }

        try {
            $newPing = microtime(true);
            if (!$this->lastPing || (0.45 - ($newPing - $this->lastPing)) < 0) {
                $ping = $this->client->ping();
                $this->lastPing = $newPing;
            } else {
                // already pinged recently (within 450ms)
                $ping = true;
            }
        } catch (Throwable $e) {
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
        $p = $conf['persistent'] ?? RedisClientInterface::DEFAULTS['persistent'];

        return new self($host, $port, $pwd, $scheme, $db, $p);
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
            if (!$this->checkRedisClientDB()) {

                return $this->selectDatabase($this->context['database']);
            }
        } catch (ConnectionLostException $cle) {
            throw $cle;
        } catch (Throwable $e) {
            $this->formatException($e);

            return false;
        }

        return true;
    }

    /**
     * The CLIENT LIST command returns information and statistics about the client <b>connections</b> server in a mostly human readable format.
     *
     * return client context stored "remotely" on redis server
     *
     * @return array
     * @throws LogicException
     */
    public function getClientCtxtFromRemote(): array
    {
        $list = $this->clientList();
        while (count($list)) {
            $context = array_pop($list);
            if (!isset($context['id']) || !isset($context['db']) || !isset($context['cmd'])) {
                throw new LogicException('redis server returned inconsistent data');
            }
            if ($this->client->isPersistent()) {
                if (intval($context['id']) === $this->context['client_id']) {
                    return $context;
                }
            } else {
                /**
                 * @todo find a better workaround
                 */
                if (strpos($context['cmd'], 'client') === 0) {
                    return $context;
                }
            }
        }

        if (!$context) {
            throw new LogicException('redis server returned no data');
        }

        return $context;
    }

    /**
     * @throws ConnectionLostException
     */
    protected function throwCLEx(): void
    {
        if ($this->lastErrorMsg) {
            throw new ConnectionLostException($this->lastErrorMsg);
        }

        throw new ConnectionLostException();
    }

    /**
     * @throws LocalIntegrityException
     */
    protected function throwLIEx(): void
    {
        if ($this->lastErrorMsg) {
            throw new LocalIntegrityException($this->lastErrorMsg);
        }

        throw new LocalIntegrityException();
    }

    /**
     * @throws UnexpectedException
     */
    protected function throwUEx(): void
    {
        if ($this->lastErrorMsg) {
            throw new UnexpectedException($this->lastErrorMsg);
        }

        throw new UnexpectedException();
    }

    /**
     *
     * @param Throwable $t
     */
    protected function formatException(Throwable $t): void
    {
        $debug = '';
        if (defined('LLEGAZ_DEBUG')) {
            $debug = PHP_EOL . $t->getTraceAsString();
        }
        $this->lastErrorMsg = $t->getMessage() . $debug . PHP_EOL;
    }

    /**
     * check the client <b>connection</b> ID stored by remote Redis server
     *
     * @param mixed $mixed
     * @return bool
     */
    public function checkRedisClientId($mixed = null): bool
    {
        if (!$mixed) {
            $mixed = $this->getClientCtxtFromRemote()['id'];
        }

        return (intval($mixed) === $this->context['client_id']);
    }

    /**
     * check the client DB stored by remote Redis server
     *
     * @param mixed $mixed  the payload['db'] returned by CLIENT LIST command
     *                     (normally a string with predis, int with phpredis)
     * @return bool
     */
    public function checkRedisClientDB($mixed = null): bool
    {
        if (!$mixed) {
            $mixed = $this->getClientCtxtFromRemote()['db'];
        }

        return (intval($mixed) === $this->context['database']);
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
     * fetch id managed by remote Redis server for the ongoing <b>connection</b>
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
