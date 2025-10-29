<?php

declare(strict_types=1);

namespace LLegaz\Redis\Tests\Functional;

use LLegaz\Redis\Exception\ConnectionLostException;
use LLegaz\Redis\RedisAdapter as SUT;
use LLegaz\Redis\RedisClientInterface;
use LLegaz\Redis\RedisClientsPool;
use LLegaz\Redis\Tests\TestState;

if (!defined('SKIP_FUNCTIONAL_TESTS')) {
    define('SKIP_FUNCTIONAL_TESTS', true);
}

/**
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

    /**
     * all servers conf (local default+dockers)
     */
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

    protected const DB_COUNT = 16;

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
        if (!TestState::$adapterClassDisplayed) {
            TestState::$adapterClassDisplayed = true;
            dump($this->redisAdapter->getRedis()->toString() . ' adapter used.');
        }
        /**
         * retrieve initial clients count, resulting expected count will be calculated from it
         * (hazardous)
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
            for ($i = 0; $i < self::DB_COUNT; $i++) {
                $this->assertTrue($this->redisAdapter->selectDatabase($i));
                $this->assertEquals($i, $this->redisAdapter->getClientCtxtFromRemote()['db']);
            }
        }
        $this->assertEquals(count(self::DOCKERS), RedisClientsPool::clientCount());
    }

    public function testRedisClientSwitchRemotes()
    {
        for ($i = 0; $i < self::DB_COUNT; $i++) {
            foreach (self::DOCKERS as $cnfg) {
                $cnfg['database'] = $i;
                $this->redisAdapter = SUT::createRedisAdapter($cnfg);
                /***
                 * @warning if you are not in paranoid mode (default mode) you have to handle your db switch by yourself
                 */
                if (!$this->redisAdapter->amiParanoid()) {
                    $this->redisAdapter->selectDatabase($i);
                }
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

    /**
     * @todo maybe rework this (can fail due to hazard LMAO)
     *
     * because next tests based on <code>getRedisClientID</code> need different ids
     * but Redis gives ids in a linear way : client 1 id = 1
     *                                       client 2 id = 2, and so on.
     */
    public function testStupidClientInvokation()
    {
        $i = 36;
        $cfg = self::DEFAULTS;
        while ($i--) {
            $j = mt_rand(1, 3);
            $cfg['port'] = self::DOCKERS[$j]['port'];
            $cfg['password'] = self::DOCKERS[$j]['password'];
            $cfg['database'] = $i % 16;
            $test = SUT::createRedisAdapter($cfg);
            $this->assertTrue($test->isConnected());
        }
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
        $init = [3, 3, 4,12,];
        $instances = [];
        foreach ($init as $db) {
            $cfg['database'] = $db;
            $sut = SUT::createRedisAdapter($cfg);
            if ($db === 4) {
                $test = $sut;
            }
            $instances[] = $sut->getID();
        }

        $this->assertNotNull($test);
        /**
         * check that all our instantiated adapters share the same client object
         */
        $i = 0;
        while (count($instances) > 1) {
            $instance = array_pop($instances);
            foreach ($instances as $other) {
                $this->assertEquals($instance, $other);
                $i++;
            }
        }
        // modify this if you alter $init
        $this->assertEquals(6, $i);
        /**
         * ensure the same client is reused (by different objects)
         */
        $this->assertNotEquals($this->redisAdapter, $test);
        $this->assertEquals($this->clientsCount, count($this->redisAdapter->clientList()));
        $this->assertEquals($this->clientsCount, count($test->clientList()));
        $this->assertEquals($this->redisAdapter->getID(), $test->getID());
        $this->assertEquals($this->redisAdapter->getRedisClientID(), $test->getRedisClientID());
        $this->assertEquals(1, RedisClientsPool::clientCount()); // 1 client to rule them all

        /***
          * @warning if you are not in paranoid mode (default mode) you have to handle your db switch by yourself
          */
        if ($test->amiParanoid()) {
            $this->assertTrue($test->checkIntegrity());
        } else {
            $test->selectDatabase(4); // yeah I know it sucks :p
        }

        // sugar..
        $this->assertTrue($test->checkRedisClientDB());
        $this->assertEquals(4, $test->getClientCtxtFromRemote()['db']);
    }

    /**
     * More testing of <b>RedisClientsPool</b> clients singleton management through RedisAdapter Class:
     * multiple clients are instantiated and should be retrieved through the  <b>RedisClientsPool</b> helper.
     */
    public function testMultipleClientsInvokationConsistency()
    {
        $a = $b = [];
        foreach (self::DOCKERS as $cfg) {
            if (isset($cfg['port']) && isset($cfg['password'])) {
                $newInstance = new SUT(self::DEFAULTS['host'], $cfg['port'], $cfg['password']);
                $a[] = [$newInstance, $newInstance->getID()];
            } else {
                $a[] = [$this->redisAdapter, $this->redisAdapter->getID()];
            }
        }
        $this->assertEquals(RedisClientsPool::clientCount(), count($a));
        $i = 0;
        while (count($a)) {
            list($sut, $id) = array_pop($a);
            $b[] = $sut;
            $this->assertTrue($sut->isConnected());
            foreach ($a as list($other, $other_id)) {
                $this->assertNotEquals($id, $other_id);
                $this->assertNotEquals($sut, $other);
                $this->assertNotEquals($sut->getID(), $other->getID());
                $i++;
            }
        }
        $this->assertEquals(6, $i);

        foreach ($b as $sut) {
            $ctx = $sut->getContext();
            $testAgain = new SUT($ctx['host'], $ctx['port'], $ctx['password'] ?? null, $ctx['scheme'], $i);
            $this->assertTrue($testAgain->isConnected());
            $this->assertTrue($sut->isConnected());
            $this->assertEquals($sut->getID(), $testAgain->getID());
            $this->assertEquals($sut->getRedisClientID(), $testAgain->getRedisClientID());
        }
        $this->assertEquals(count(self::DOCKERS), RedisClientsPool::clientCount());

        // create new clients with persistent conns
        foreach ($b as $sut) {
            $ctx = $sut->getContext();
            $testAgain = new SUT($ctx['host'], $ctx['port'], $ctx['password'] ?? null, $ctx['scheme'], $i, true);
            $this->assertTrue($testAgain->isConnected());
            $this->assertTrue($sut->isConnected());
            $this->assertNotEquals($sut->getID(), $testAgain->getID());
            $this->assertNotEquals($sut->getRedisClientID(), $testAgain->getRedisClientID());
        }
        $this->assertEquals(count(self::DOCKERS) * 2, RedisClientsPool::clientCount());
    }

    /**
     * multi clients / multi servers
     *
     * @todo refacto using persistent connections (up to 8 clients simultaneous)
     *              and split this test into smaller parts if possible..
     *              Not a high priority tho.
     *              Check <code>testPersistentConnsAreReusedOnNextInvokation</code>
     *              to do something similar but inverted and make sure that indeed
     *              redis clients aren't reused on next invocation (once clients are
     *              purged from setup non-persistent connections aren't reused)
     */
    public function testRedisAdapterDBsWithMultiConnections()
    {
        $a = [];
        foreach (self::DOCKERS as $cfg) {
            if (count($cfg)) {
                $newInstance = SUT::createRedisAdapter($cfg);
                $a[] = [$newInstance, count($newInstance->clientList())];
            } else {
                $a[] = [$this->redisAdapter, count($this->redisAdapter->clientList())];
            }
        }
        for ($i = 0; $i < self::DB_COUNT; $i++) {
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
        foreach (self::DOCKERS as $cfg) {
            if (count($cfg)) {
                $cfg['database'] = mt_rand(1, 13);
                $newInstance = SUT::createRedisAdapter($cfg);
                $a[] = [$newInstance, count($newInstance->clientList())];
            }
        }
        // here we have dupplicates (3 new adapter instances reusing previously instantiated redis client singletons)
        $this->assertNotEquals(RedisClientsPool::clientCount(), count($a));

        $final = [];
        foreach ($a as list($pa, $cnt)) {
            // both adapters use the same client so whe should retrieve the same count of clients
            $this->assertEquals($cnt, count($pa->clientList()));
            $id = $pa->getID();

            // purging dupplicates
            if (isset($final[$id])) {
                // make sure different adapters use the same client(singleton) based on its state
                $this->checkRemote($final[$id], $pa);
                // client state is different that adapters' context (last adapter prevailed)
                $this->assertNotEquals(($final[$id])->getContext(), $pa->getContext());
                // reset client state to fistrly instantiated adapter context
                $this->assertTrue(($final[$id])->checkIntegrity());
                $this->checkLocal($final[$id]);
                // do the same on last instantiated adapter then testing checkIntegrity method
                $this->assertTrue($pa->checkIntegrity());
                $this->checkLocal($pa);

                // all starting connections were made on db0 (default)
                if ($pa->getRedis()->toString() === RedisClientInterface::PREDIS &&
                        ($final[$id])->getRedis()->toString() === RedisClientInterface::PREDIS) {
                    // shit works on predis only maybe remove it as it is useless check
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
     * test multiple instantiation with persistent connections
     *
     * @depends testStupidClientInvokation
     */
    public function testClientsInvokationWithPersistentConn(): array
    {
        $persistentIDs = [];
        foreach (self::DOCKERS as $cnfg) {
            $cnfg['persistent'] = true;
            $this->redisAdapter = SUT::createRedisAdapter($cnfg);
            $this->assertTrue($this->redisAdapter->checkRedisClientId());
            $pID = $this->redisAdapter->getRedisClientID();
            for ($i = 0; $i < self::DB_COUNT; $i++) {
                $this->assertTrue($this->redisAdapter->selectDatabase($i));
                $this->assertEquals($i, $this->redisAdapter->getClientCtxtFromRemote()['db']);
            }
            $this->assertTrue($this->redisAdapter->checkRedisClientId());
            $this->assertTrue($this->redisAdapter->checkRedisClientId($pID));
            $persistentIDs[] = $pID;
        }
        // 4 persistent clients + 1 defaulf redisAdapter instantiated on set up
        $this->assertEquals(count(self::DOCKERS) + 1, RedisClientsPool::clientCount());

        return $persistentIDs;
    }

    /**
     * @depends testClientsInvokationWithPersistentConn
     */
    public function testClientsInvokationWithPersistentConn2(array $persistentIDs): array
    {
        // clear singletons pool
        RedisClientsPool::destruct();
        foreach (self::DOCKERS as $cnfg) {
            $cnfg['persistent'] = true;
            $cnfg['database'] = 13;
            $this->redisAdapter = SUT::createRedisAdapter($cnfg);
            $this->assertTrue($this->redisAdapter->checkRedisClientId());
            $pID = $this->redisAdapter->getRedisClientID();
            $this->assertTrue(
                // skip predis.. not reusing persistent connections properly ?
                $this->redisAdapter->getRedis()->toString() === RedisClientInterface::PREDIS ||
                in_array($pID, $persistentIDs)
            );
            $this->assertTrue($this->redisAdapter->checkRedisClientId($pID));
        }
        // 4 persistent clients only
        $this->assertEquals(count(self::DOCKERS), RedisClientsPool::clientCount());

        return $persistentIDs;
    }

    /**
     * @depends testClientsInvokationWithPersistentConn2
     */
    public function testPersistentConnsAreReusedOnNextInvokation(array $persistentIDs)
    {
        $previousID = null;
        foreach (self::DOCKERS as $cnfg) {
            $cnfg['persistent'] = true;
            $this->redisAdapter = SUT::createRedisAdapter($cnfg);
            $pID = $this->redisAdapter->getRedisClientID();
            $this->assertTrue(in_array($pID, $persistentIDs));
            if ($previousID) {
                $this->assertNotEquals($previousID, $pID);
            }
            $previousID = $pID;
        }
        // 4 persistent clients + 1 defaulf redisAdapter instantiated on set up
        $this->assertEquals(count(self::DOCKERS) + 1, RedisClientsPool::clientCount());
    }

    /**
     * @expectedException LLegaz\Redis\Exception\ConnectionLostException
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
        // all these tests only have sense if in a consistent enforced context
        if ($a->amiParanoid()) {
            $local = $a->getContext()['database'];
            $remote = $a->getClientCtxtFromRemote()['db'];
            $this->assertIsInt($local);
            $this->assertRemote($a, $remote);
            $this->assertTrue($local === intval($remote));
            $this->assertTrue($a->checkRedisClientDB($remote));
        }
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
