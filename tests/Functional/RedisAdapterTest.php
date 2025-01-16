<?php

declare(strict_types=1);

namespace LLegaz\Redis\Tests\Functional;

use LLegaz\Redis\Exception\ConnectionLostException;
use LLegaz\Redis\RedisAdapter as SUT;
use LLegaz\Redis\RedisClientInterface;
use LLegaz\Redis\RedisClientsPool;

if (!defined('SKIP_FUNCTIONAL_TESTS')) {
    define('SKIP_FUNCTIONAL_TESTS', true);
}

/**
 * @todo refacto tests
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisAdapterTest extends \PHPUnit\Framework\TestCase
{
    /** @var RedisAdapter */
    protected SUT $redisAdapter;

    protected int $clientsCount;

    /**
     * DEFAULTS to access local redis-server
     *
     * @var array
     */
    protected const DEFAULTS = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'scheme' => 'tcp',
        'database' => 0,
    ];

    protected const DOCKERS = [
        [],
        [
            'port' => 6375,
            'password' => 'RedisAuth1',
        ],
        [
            'port' => 6376,
            'password' => 'RedisAuth2',
            ],
        [
            'port' => 6377,
            'password' => 'RedisAuth3',
        ],
    ];

    /**
     * @inheritdoc
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        if (SKIP_FUNCTIONAL_TESTS) {
            // don't forget that tests are deleoppers' tools (and not only an approval seal)
            $this->markTestSkipped('FUNCTIONAL TESTS are skipped by default when executing Units tests only.');
        }
        // clear singletons pool
        RedisClientsPool::destruct();
        $this->redisAdapter = new SUT();
        /**
         * retrieve initial clients count, resulting expected count will be calculated from it
         */
        $this->clientsCount = count($this->redisAdapter->clientList());
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        unset($this->redisAdapter);
    }

    /**
     * Functional test on the predis adapter
     *
     * do some basics checks on redis communication success
     * for some basic scenarios
     *
     * 1 - single instance
     * 2 - single instance / multi-db
     * 3 - multi-instances
     * 4 - multi-instances / multi-db
     *
     *
     * to run these test you need a redis server and docker installed
     */
    public function testRedisAdapterFunc()
    {
        $this->assertTrue($this->redisAdapter->isConnected());
    }

    public function testRedisClientSwitchDBs()
    {
        foreach (self::DOCKERS as $cnfg) {
            if (isset($cnfg['port']) && isset($cnfg['password'])) {
                $this->redisAdapter = SUT::createRedisAdapter($cnfg);
            }
            for ($i = 0; $i < 16; $i++) {
                $this->assertTrue($this->redisAdapter->selectDatabase($i));
                $this->assertEquals($i, $this->redisAdapter->getClientCtxtFromRemote()['db']);
            }
        }
        $this->assertEquals(count(self::DOCKERS), RedisClientsPool::clientCount());
    }

    public function testRedisClientSwitchRemotes()
    {
        for ($i = 0; $i < 3; $i++) {
            foreach (self::DOCKERS as $cnfg) {
                $cnfg['database'] = $i;
                $this->redisAdapter = SUT::createRedisAdapter($cnfg);
                $remoteDB = $this->redisAdapter->getClientCtxtFromRemote()['db'];
                $this->assertRemote($this->redisAdapter, $remoteDB);
                $this->assertEquals($i, intval($remoteDB));
            }
        }
        $this->assertEquals(count(self::DOCKERS), RedisClientsPool::clientCount());
    }

    /**
     * the same client SHOULD BE used when trying to instantiate another adapter object
     *  to address another database on a already visited (or connected) server.
     */
    public function testClientInvokationConstistency()
    {
        $cfg = self::DEFAULTS;
        $cfg['database'] = 3;
        $test = SUT::createRedisAdapter($cfg);
        $otherClientID = $test->getID();
        $this->assertEquals($this->redisAdapter->getID(), $otherClientID);
        $this->assertEquals($this->clientsCount, count($test->clientList()));
        $otherClientID = $test->getRedisClientID();
        $this->assertEquals($this->redisAdapter->getRedisClientID(), $otherClientID);
    }

    public function testEmptyClientsPool()
    {
        $this->assertEquals(1, RedisClientsPool::clientCount());
        if (gc_enabled()) {
            // make sure to clear singletons pool
            RedisClientsPool::destruct();
            $this->assertGreaterThanOrEqual(0, gc_collect_cycles());
            $this->assertEquals(0, RedisClientsPool::clientCount());
        }
    }

    /**
     * in fact those tests (previous too) are testing <b>RedisClientsPool</b> class
     */
    public function testSingleClientInvokationConsistency()
    {
        $cfg = self::DEFAULTS;
        $cfg['database'] = 3;
        $un = SUT::createRedisAdapter($cfg)->getID();
        $deux = SUT::createRedisAdapter($cfg)->getID();
        $cfg['database'] = 4;
        $test = SUT::createRedisAdapter($cfg);
        $trois = $test->getID();
        $cfg['database'] = 12;
        $quatre = SUT::createRedisAdapter($cfg)->getID();
        $this->assertEquals($un, $deux);
        $this->assertEquals($un, $trois);
        $this->assertEquals($un, $quatre);
        $this->assertEquals($deux, $trois);
        $this->assertEquals($deux, $quatre);
        $this->assertEquals($trois, $quatre);
        /**
         * ensure the same client is reused (by different objects)
         */
        $this->assertNotEquals($this->redisAdapter, $test);
        $this->assertEquals($this->clientsCount, count($this->redisAdapter->clientList()));
        $this->assertEquals($this->clientsCount, count($test->clientList()));
        $this->assertEquals($this->redisAdapter->getID(), $test->getID());
        $this->assertEquals($this->redisAdapter->getRedisClientID(), $test->getRedisClientID());
        $this->assertEquals(1, RedisClientsPool::clientCount()); // 1 client to rule them all

        // sugar..
        $this->assertTrue($test->checkIntegrity());
        $this->assertEquals(4, $test->getClientCtxtFromRemote()['db']);
    }

    /**
     * More testing of <b>RedisClientsPool</b> clients singleton management through RedisAdapter Class:
     * multiple clients are instantiated and should be retrieved through the  <b>RedisClientsPool</b> helper.
     *
     * @todo refacto
     */
    public function testMultipleClientsInvokationConsistency()
    {
        $un = $this->redisAdapter->getID();

        $test = new SUT('127.0.0.1', 6375, 'RedisAuth1');
        $deux = $test->getID();

        $test2 = new SUT('127.0.0.1', 6376, 'RedisAuth2');
        $trois = $test2->getID();

        $test3 = new SUT('127.0.0.1', 6377, 'RedisAuth3');
        $quatre = $test3->getID();

        $this->assertTrue($test->isConnected());
        $this->assertTrue($test2->isConnected());
        $this->assertTrue($test3->isConnected());


        $this->assertEquals(count(self::DOCKERS), RedisClientsPool::clientCount());

        $this->assertNotEquals($un, $deux);
        $this->assertNotEquals($un, $trois);
        $this->assertNotEquals($un, $quatre);
        $this->assertNotEquals($deux, $trois);
        $this->assertNotEquals($deux, $quatre);
        $this->assertNotEquals($trois, $quatre);
        $i = 3;
        while ($i > 0) {
            $testAgain = new SUT('127.0.0.1', 6374 + $i, 'RedisAuth' . $i, 'tcp', $i);
            $this->assertTrue($testAgain->isConnected());
            $this->assertEquals(count(self::DOCKERS), RedisClientsPool::clientCount());
            if ($i === 1) {
                $i = '';
            }
            $this->assertEquals(${"test$i"}->getID(), $testAgain->getID());
            $this->assertEquals(${"test$i"}->getRedisClientID(), $testAgain->getRedisClientID());
            $this->assertNotEquals($this->redisAdapter->getRedisClientID(), $testAgain->getRedisClientID());
            $i--;
        }
    }

    /**
     * multi clients / multi servers
     *
     * @todo refacto
     */
    public function testRedisAdapterDBsWithMultiConnections()
    {
        $cfg = self::DEFAULTS;
        $a = [];
        $a[] = [$this->redisAdapter, count($this->redisAdapter->clientList())];
        $cfg['port'] = 6375;
        $cfg['password'] = 'RedisAuth1';
        $newInstance = SUT::createRedisAdapter($cfg);
        $a[] = [$newInstance, count($newInstance->clientList())];
        $cfg['port'] = 6376;
        $cfg['password'] = 'RedisAuth2';
        $newInstance = SUT::createRedisAdapter($cfg);
        $a[] = [$newInstance, count($newInstance->clientList())];
        $cfg['port'] = 6377;
        $cfg['password'] = 'RedisAuth3';
        $newInstance = SUT::createRedisAdapter($cfg);
        $a[] = [$newInstance, count($newInstance->clientList())];
        for ($i = 0; $i < 16; $i++) {
            foreach ($a as list($pa, $cnt)) {
                $this->assertTrue($pa->isConnected());
                $this->assertTrue($pa->selectDatabase($i));
                // make sure we have only 1 more client per client/server pair
                $this->assertEquals($cnt, count($pa->clientList()));
                $this->assertEquals($i, $pa->getClientCtxtFromRemote()['db']);
            }
        }
        // 4 clients (pairing with 4 servers)
        $this->assertEquals(RedisClientsPool::clientCount(), count($a));
        /**
         * then add more adapter objects
         */
        $cfg['port'] = 6375;
        $cfg['password'] = 'RedisAuth1';
        $cfg['database'] = 3;
        $newInstance = SUT::createRedisAdapter($cfg);
        $a[] = [$newInstance, count($newInstance->clientList())];
        $cfg['port'] = 6376;
        $cfg['password'] = 'RedisAuth2';
        $cfg['database'] = 4;
        $newInstance = SUT::createRedisAdapter($cfg);
        $a[] = [$newInstance, count($newInstance->clientList())];
        $cfg['port'] = 6377;
        $cfg['password'] = 'RedisAuth3';
        $cfg['database'] = 9;
        $newInstance = SUT::createRedisAdapter($cfg);
        $a[] = [$newInstance, count($newInstance->clientList())];
        // here we have dupplicates (3 new instances which weren't really instantiated)
        $this->assertNotEquals(RedisClientsPool::clientCount(), count($a));

        $final = [];
        foreach ($a as list($pa, $cnt)) {
            $this->assertEquals($cnt, count($pa->clientList()));
            $id = $pa->getID();

            // same referenced clients SHOULD have the same final state
            if (isset($final[$id])) {
                // here real predis client are sync (singletons)
                $this->checkRemote($final[$id], $pa);

                // but redisAdapter instances' contexts are not
                $this->assertNotEquals(($final[$id])->getContext(), $pa->getContext());

                /**
                 * j'avoue là on se tire les cheveux.. intégrité quand tu nous tiens
                 */
                $this->assertTrue(($final[$id])->checkIntegrity());
                $this->checkLocal($final[$id]);
                // all starting connections were made on db0 (default) and they theorically should be the first elements in final array (this works only fro predis)

                $this->assertTrue($pa->checkIntegrity());
                $this->checkLocal($pa);

                // all starting connections were made on db0 (default)
                if ($pa->getRedis()->toString() === RedisClientInterface::PREDIS && ($final[$id])->getRedis()->toString() === RedisClientInterface::PREDIS) {
                    $this->assertEquals(0, ($final[$id])->getRedis()->getConnection()->getParameters()->toArray()['database']);
                    $this->assertEquals(0, $pa->getRedis()->getConnection()->getParameters()->toArray()['database']);
                }
                // second batch of connections reusing singletons were not made on db0 (original contexts are overwritten by new instances)
                $this->assertNotEquals(0, ($final[$id])->getClientCtxtFromRemote()['db']);
                $this->assertNotEquals(0, $pa->getClientCtxtFromRemote()['db']);
                // singletons
                $this->assertEquals($pa->getRedisClientID(), ($final[$id])->getRedisClientID());
            } else {
                $final[$id] = clone $pa;
            }
        }
        // dupplicates were eliminated
        $this->assertEquals(RedisClientsPool::clientCount(), count($final));
    }

    /**
     * @expectedException LLegaz\Predis\Exception\ConnectionLostException
     */
    public function testClientInvokationWithAuthException(): ?SUT
    {
        $this->expectException(ConnectionLostException::class);
        $test = new SUT('127.0.0.1', 6375, 'wrong password');

        return $test;
    }

    /**
     * @depends testClientInvokationWithAuthException
     */
    public function testClientAfterAuthException(?SUT $test)
    {
        $this->assertNull($test);
    }

    public function testClientInvokationWithUnreachableException(): ?SUT
    {
        $this->expectException(ConnectionLostException::class);
        $test = new SUT('127.0.1.0', 6375, 'wrong IP address');

        return $test;
    }

    /**
     * @depends testClientInvokationWithUnreachableException
     */
    public function testClientAfterUnreachableException(?SUT $test)
    {
        $this->assertNull($test);
    }

    private function checkRemote(SUT $a, SUT $b): void
    {
        $remoteA = $a->getClientCtxtFromRemote()['db'];
        $remoteB = $b->getClientCtxtFromRemote()['db'];
        $this->assertRemote($a, $remoteA);
        $this->assertRemote($b, $remoteB);
        $this->assertEquals($remoteA, $remoteB);
    }

    private function checkLocal(SUT $a): void
    {
        $local = $a->getContext()['database'];
        $remote = $a->getClientCtxtFromRemote()['db'];
        $this->assertIsInt($local);
        $this->assertRemote($a, $remote);
        $this->assertTrue($local === intval($remote));
        $this->assertTrue($a->checkRedisClientDB($remote));
    }

    private function assertRemote(SUT $sut, $remote): void
    {
        if ($sut->getRedis()->toString() === RedisClientInterface::PREDIS) {
            $this->assertIsString($remote);
        } else {
            $this->assertIsInt($remote);
        }
    }
}
