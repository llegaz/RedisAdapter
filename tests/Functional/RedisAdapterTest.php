<?php

declare(strict_types=1);

namespace LLegaz\Redis\Tests\Functional;

use LLegaz\Redis\Exception\ConnectionLostException;
use LLegaz\Redis\RedisAdapter as SUT;
use LLegaz\Redis\RedisClientsPool;

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
    protected $redisAdapter;

    /** @var array */
    protected const DEFAULTS = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'scheme' => 'tcp',
        'database' => 0,
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
        $this->redisAdapter = new SUT();
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
        for ($i = 0; $i < 16; $i++) {
            $this->assertTrue($this->redisAdapter->selectDatabase($i));
            $this->assertEquals($i, $this->pop_helper($this->redisAdapter)['db']);
        }
    }

    /**
     * @todo refacto this shit use both  getRedisClientID and getThisClientID
     * maybe rename getThisClientID to getLocalRedisClientID  or getClientHash or getClientIntegrityID
     *          and getRedisClientID to getRemoteRedisClientID
     * the same client SHOULD BE used when trying to instantiate another adapter to address another database
     */
    public function testClientInvokationConstistency()
    {
        $cfg = self::DEFAULTS;
        $cfg['database'] = 3;
        $test = SUT::createRedisAdapter($cfg);
        $otherClientID = $test->getThisClientID();
        $this->assertEquals($this->redisAdapter->getThisClientID(), $otherClientID);
        $this->assertEquals(1, count($this->redisAdapter->clientList()));
        $otherClientID = $test->getRedisClientID();
        $this->assertEquals($this->redisAdapter->getRedisClientID(), $otherClientID);
    }

    /**
     * in fact those tests (previous too) are testing <b>RedisClientsPool</b> class
     */
    public function testSingleClientInvokationConsistency()
    {
        $cfg = self::DEFAULTS;
        $cfg['database'] = 3;
        $un = SUT::createRedisAdapter($cfg)->getThisClientID();
        $deux = SUT::createRedisAdapter($cfg)->getThisClientID();
        $cfg['database'] = 4;
        $test = SUT::createRedisAdapter($cfg);
        $trois = $test->getThisClientID();
        $cfg['database'] = 3;
        $test = SUT::createRedisAdapter($cfg);
        $quatre = $test->getThisClientID();
        $this->assertEquals($un, $deux);
        $this->assertEquals($un, $trois);
        $this->assertEquals($un, $quatre);
        $this->assertEquals($deux, $trois);
        $this->assertEquals($deux, $quatre);
        $this->assertEquals($trois, $quatre);
        // those may fail if local redis is used at the same time
        $this->assertEquals(1, count($this->redisAdapter->clientList())); // 1conn
        $this->assertEquals(1, count($test->clientList())); // 1conn
        $this->assertEquals($this->redisAdapter->getThisClientID(), $test->getThisClientID()); // 1conn
    }

    /**
     * Testing <b>RedisClientsPool</b> clients singleton handling though RedisAdapter Class
     * multiple clients are instantiated and should be retrieved through the  <b>RedisClientsPool</b> helper
     */
    public function testMultipleClientsInvokationConsistency()
    {
        $un = $this->redisAdapter->getThisClientID();

        $test = new SUT('127.0.0.1', 6375, 'RedisAuth1');
        $deux = $test->getThisClientID();

        $test2 = new SUT('127.0.0.1', 6376, 'RedisAuth2');
        $trois = $test2->getThisClientID();

        $test3 = new SUT('127.0.0.1', 6377, 'RedisAuth3');
        $quatre = $test3->getThisClientID();

        $this->assertTrue($test->isConnected());
        $this->assertTrue($test2->isConnected());
        $this->assertTrue($test3->isConnected());


        $this->assertEquals(RedisClientsPool::clientCount(), 4);

        $this->assertNotEquals($un, $deux);
        $this->assertNotEquals($un, $trois);
        $this->assertNotEquals($un, $quatre);
        $this->assertNotEquals($deux, $trois);
        $this->assertNotEquals($deux, $quatre);
        $this->assertNotEquals($trois, $quatre);
        $i = 3;
        while ($i > 0) {
            $testAgain = new SUT('127.0.0.1', 6374 + $i, 'RedisAuth' . $i);
            $this->assertTrue($testAgain->isConnected());
            $this->assertEquals(RedisClientsPool::clientCount(), 4);
            if ($i === 1) {
                $i = '';
            }
            $this->assertEquals(${"test$i"}->getThisClientID(), $testAgain->getThisClientID());
            $this->assertEquals(${"test$i"}->getRedisClientID(), $testAgain->getRedisClientID());
            $this->assertNotEquals($this->redisAdapter->getRedisClientID(), $testAgain->getRedisClientID());
            $i--;
        }
    }

    /**
     * multi clients / multi servers
     */
    public function testRedisAdapterDBsWithMultiConnections()
    {
        $cfg = self::DEFAULTS;
        $a = [];
        $a[] = $this->redisAdapter;
        $cfg['port'] = 6375;
        $cfg['password'] = 'RedisAuth1';
        $a[] = SUT::createRedisAdapter($cfg);
        $cfg['port'] = 6376;
        $cfg['password'] = 'RedisAuth2';
        $a[] = SUT::createRedisAdapter($cfg);
        $cfg['port'] = 6377;
        $cfg['password'] = 'RedisAuth3';
        $a[] = SUT::createRedisAdapter($cfg);
        for ($i = 0; $i < 16; $i++) {
            foreach ($a as $pa) {
                $this->assertTrue($pa->isConnected());
                $this->assertTrue($pa->selectDatabase($i));
                // make sure we have 1 client per client/server pair
                $this->assertEquals(1, count($pa->clientList()));
                $this->assertEquals($i, $this->pop_helper($pa)['db']);
            }
        }
        // 4 clients (pairing with 4 servers)
        $this->assertEquals(RedisClientsPool::clientCount(), count($a));
        $cfg['port'] = 6375;
        $cfg['password'] = 'RedisAuth1';
        $cfg['database'] = 3;
        $a[] = SUT::createRedisAdapter($cfg);
        $cfg['port'] = 6376;
        $cfg['password'] = 'RedisAuth2';
        $cfg['database'] = 4;
        $a[] = SUT::createRedisAdapter($cfg);
        $cfg['port'] = 6377;
        $cfg['password'] = 'RedisAuth3';
        $cfg['database'] = 9;
        $a[] = SUT::createRedisAdapter($cfg);
        // here we have dupplicates (3 new instances which weren't really instantiated)
        $this->assertNotEquals(RedisClientsPool::clientCount(), count($a));

        $final = [];
        foreach ($a as $pa) {
            $id = $pa->getThisClientID();

            // same referenced clients SHOULD have the same final state
            if (isset($final[$id])) {
                // here real predis client are sync (singletons)
                $this->assertEquals($this->pop_helper($final[$id])['db'], $this->pop_helper($pa)['db']);

                // but redisAdapter instances' contexts are not
                $this->assertNotEquals(($final[$id])->getContext(), $pa->getContext());

                /**
                 * j'avoue là on se tire les cheveux.. intégrité quand tu nous tiens
                 */
                $this->assertTrue(($final[$id])->checkIntegrity());
                $this->assertEquals(($final[$id])->getContext()['database'], $this->pop_helper($final[$id])['db']);
                // all starting connections were made on db0 (default) and they theorically should be the first elements in final array

                $this->assertTrue($pa->checkIntegrity());
                $this->assertEquals($pa->getContext()['database'], $this->pop_helper($pa)['db']);

                // all starting connections were made on db0 (default)
                $this->assertEquals(0, ($final[$id])->getRedis()->getConnection()->getParameters()->toArray()['database']);
                $this->assertEquals(0, $pa->getRedis()->getConnection()->getParameters()->toArray()['database']);
                // second batch of connections reusing singletons were not made on db0 (original contexts are overwritten by new instances)
                $this->assertNotEquals(0, $this->pop_helper($final[$id])['db']);
                $this->assertNotEquals(0, $this->pop_helper($pa)['db']);
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

    private function pop_helper(SUT $pa)
    {
        $client_list = $pa->clientList();

        return array_pop($client_list);
    }
}
