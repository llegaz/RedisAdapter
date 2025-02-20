<?php

declare(strict_types=1);

namespace LLegaz\Redis\Tests\Unit;

use LLegaz\Redis\Exception\ConnectionLostException;
use LLegaz\Redis\Exception\UnexpectedException;
use LLegaz\Redis\RedisAdapter as SUT;
use LLegaz\Redis\RedisClient;
use LLegaz\Redis\RedisClientInterface;

/**
 * RC = Redis client instead of predis
 *
 *
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisAdapterRCTest extends \LLegaz\Redis\Tests\RedisAdapterTestBase
{
    protected RedisClientInterface $redisClient;

    protected function setUp(): void
    {
        if (!in_array('redis', get_loaded_extensions())) {
            $this->markTestSkipped('Skip those units as php-redis extension is not loaded.');
        }

        parent::setUp();

        $this->redisClient = $this->getMockBuilder(RedisClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'client',
                'disconnect',
                'isConnected',
                'launchConnection',
                'ping',
                'select',
            ])
            ->getMock()
        ;

        $this->redisClient
            ->expects($this->any())
            ->method('isConnected')
            ->willReturn(true)
        ;
        $this->redisClient
            ->expects($this->any())
            ->method('disconnect')
            ->willReturnSelf()
        ;
        $this->redisClient
            ->expects($this->any())
            ->method('ping')
            ->willReturn(true)
        ;
        $this->redisAdapter = new SUT(
            RedisClientInterface::DEFAULTS['host'],
            RedisClientInterface::DEFAULTS['port'],
            null,
            RedisClientInterface::DEFAULTS['scheme'],
            RedisClientInterface::DEFAULTS['database'],
            false,
            $this->redisClient
        );
        $this->assertDefaultContext();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testDummy()
    {
        $this->assertTrue(true);
    }

    public function testIsConnected()
    {
        $this->redisClient->expects($this->once())
                ->method('ping')
                ->willReturn(true)
        ;

        $this->assertTrue($this->redisAdapter->isConnected());
        $this->assertDefaultContext();
    }

    public function testSelectDB()
    {
        $this->redisClient->expects($this->once())
                ->method('select')
                ->willReturn(true)
        ;

        $this->assertTrue($this->redisAdapter->selectDatabase(42));
        $this->assertNotDefaultContext();
    }

    public function testClientList()
    {
        $this->redisClient->expects($this->once())
            ->method('client')
            ->with('list')
            ->willReturn([])
        ;

        $this->assertTrue(is_array($this->redisAdapter->clientList()));
        $this->assertDefaultContext();
    }

    /**
     * @expectedException LLegaz\Redis\Exception\ConnectionLostException
     */
    public function testSelectDBException()
    {
        $this->redisClient->expects($this->once())
            ->method('ping')
            ->willThrowException(new \Exception('Tests suite\'s Exception'))
        ;

        $this->expectException(ConnectionLostException::class);
        $this->redisAdapter->selectDatabase(42);
    }

    /**
     * @expectedException LLegaz\Redis\Exception\UnexpectedException
     */
    public function testSelectDBUnexpectedException()
    {
        $this->redisClient->expects($this->once())
            ->method('select')
            ->willThrowException(new \Exception('Tests suite\'s Exception'))
        ;

        $this->expectException(UnexpectedException::class);
        $this->redisAdapter->selectDatabase(42);
    }

    /**
     * @expectedException \LogicException
     */
    public function testSelectDBLogicException()
    {
        $this->expectException(\LogicException::class);
        $this->redisAdapter->selectDatabase(-42);
    }

    /**
     * @expectedException LLegaz\Redis\Exception\ConnectionLostException
     */
    public function testClientListException()
    {
        $this->redisClient->expects($this->once())
            ->method('ping')
            ->willThrowException(new \Exception('Tests suite\'s Exception'))
        ;

        $this->expectException(ConnectionLostException::class);
        $this->redisAdapter->clientList();
    }

    /**
     * @expectedException LLegaz\Redis\Exception\UnexpectedException
     */
    public function testClientListUnexpectedException()
    {
        $this->redisClient->expects($this->once())
            ->method('client')
            ->with('list')
            ->willThrowException(new \Exception('Tests suite\'s Exception'))
        ;

        $this->expectException(UnexpectedException::class);
        $this->redisAdapter->clientList();
    }


    /**
     * @todo mb rework this (id vs client_id)
     * rework $this->assertTrue($this->redisAdapter->checkRedisClientId());
     */
    public function testCheckIntegrity()
    {
        $a = [];
        $this->assertEquals(1, array_push($a, ['id' => 1337, 'db' => 0, 'cmd' => 'client']));
        $this->redisClient->expects($this->exactly(2))
            ->method('client')
            ->with('list')
            ->willReturn($a)
        ;
        $this->assertTrue($this->redisAdapter->checkIntegrity());
        $this->assertTrue($this->redisAdapter->checkRedisClientId());
        $this->assertDefaultContext();
    }

    public function testCheckIntegritySwitchDB()
    {
        $a = [];
        $this->assertEquals(1, array_push($a, ['id' => 1337, 'db' => 10, 'cmd' => 'client']));
        $this->redisClient->expects($this->once())
                ->method('select')
                ->willReturn(true)
        ;
        $this->assertTrue($this->redisAdapter->selectDatabase(10));
        $this->redisClient->expects($this->once())
            ->method('client')
            ->with('list')
            ->willReturn($a)
        ;
        $this->assertTrue($this->redisAdapter->checkIntegrity());
        $this->assertNotDefaultContext();
    }

    /**
     * yes we could throw an exception from the later select too but it seems superfluous
     */
    public function testCheckIntegrityFail()
    {
        $this->redisClient->expects($this->once())
            ->method('client')
            ->with('list')
            ->willThrowException(new \Exception('Tests suite\'s Exception'))
        ;
        $this->assertFalse($this->redisAdapter->checkIntegrity());
    }
}
