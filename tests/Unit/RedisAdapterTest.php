<?php

declare(strict_types=1);

namespace LLegaz\Redis\Tests\Unit;

use LLegaz\Redis\Exception\ConnectionLostException;
use Predis\Response\Status;

/**
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
class RedisAdapterTest extends \LLegaz\Redis\Tests\RedisAdapterTestBase
{
    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDefaultContext();
    }
    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testDummy()
    {
        $this->assertTrue(true);
    }

    /**
     * predis part
     */
    public function testIsConnected()
    {
        $this->predisClient->expects($this->once())
                ->method('ping')
                ->willReturn(new Status('PONG'))
        ;
        $this->assertTrue($this->redisAdapter->isConnected());
        $this->assertDefaultContext();
    }

    public function testSelectDB()
    {
        $this->predisClient->expects($this->once())
                ->method('select')
                ->willReturn(new Status('OK'))
        ;
        $this->assertTrue($this->redisAdapter->selectDatabase(42));
        $this->assertNotDefaultContext();
    }

    public function testClientList()
    {
        $this->predisClient->expects($this->once())
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
        $this->predisClient->expects($this->once())
            ->method('ping')
            ->willThrowException(new \Exception())
        ;
        $this->expectException(ConnectionLostException::class);
        $this->redisAdapter->selectDatabase(42);
    }

    /**
     * @expectedException LLegaz\Redis\Exception\ConnectionLostException
     */
    public function testSelectDBExceptionAgain()
    {
        $this->predisClient->expects($this->once())
            ->method('select')
            ->willThrowException(new \Exception())
        ;
        $this->expectException(ConnectionLostException::class);
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
        $this->predisClient->expects($this->once())
            ->method('client')
            ->with('list')
            ->willThrowException(new \Exception())
        ;
        $this->expectException(ConnectionLostException::class);
        $this->redisAdapter->clientList();
    }

    /**
     *
     * same units but with redis client
     *
     *
     */
    public function testIsConnected2()
    {
        $this->redisClient->expects($this->once())
                ->method('ping')
                ->willReturn(true)
        ;
        $this->redisAdapter->setRedis($this->redisClient);
        $this->assertTrue($this->redisAdapter->isConnected());
        $this->assertDefaultContext();
    }

    public function testSelectDB2()
    {
        $this->predisClient->expects($this->once())
                ->method('select')
                ->willReturn(true)
        ;
        $this->assertTrue($this->redisAdapter->selectDatabase(42));
        $this->assertNotDefaultContext();
    }

    public function testClientList2()
    {
        $this->predisClient->expects($this->once())
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
    public function testSelectDBException2()
    {
        $this->predisClient->expects($this->once())
            ->method('select')
            ->willThrowException(new \Exception())
        ;
        $this->expectException(ConnectionLostException::class);
        $this->redisAdapter->selectDatabase(42);
    }

    /**
     * @expectedException \LogicException
     */
    public function testSelectDBLogicException2()
    {
        $this->expectException(\LogicException::class);
        $this->redisAdapter->selectDatabase(-42);
    }

    /**
     * @expectedException LLegaz\Redis\Exception\ConnectionLostException
     */
    public function testClientListException2()
    {
        $this->predisClient->expects($this->once())
            ->method('client')
            ->with('list')
            ->willThrowException(new \Exception())
        ;
        $this->expectException(ConnectionLostException::class);
        $this->redisAdapter->clientList();
    }
}
