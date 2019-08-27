<?php

declare(strict_types=1);
/**
 * This file is part of Yunhu.
 *
 * @link     https://www.yunhuyj.com/
 * @contact  zhiming.bi@yunhuyj.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */


namespace YunhuTest\DubboClient;

use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Container;
use YunhuTest\DubboClient\Stub\DubboPoolStub;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class DubboConnectionTest extends TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function testDubboConnectionConfig()
    {
        $pool = $this->getDubboPool();

        $config = $pool->get()->getConfig();

        $this->assertSame([
            'driver' => \Yunhu\DubboClient\Driver\ZookeeperDriver::class,
            'timeout' => 0.0,
            'pool' => [
                'min_connections' => 1,
                'max_connections' => 30,
                'connect_timeout' => 10.0,
                'wait_timeout' => 3.0,
                'heartbeat' => -1,
                'max_idle_time' => 1,
            ],
        ], $config);
    }

    public function testDubboConnectionReconnect()
    {
        $pool = $this->getDubboPool();

        $connection = $pool->get()->getConnection();
        $this->assertTrue($connection);
        $resut = $connection->reconnect();
        $this->assertTrue(null, $resut);

        $connection->release();
        $connection = $pool->get()->getConnection();
        $this->assertSame(null, $connection);
    }

    private function getDubboPool()
    {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('get')->once()->with(ConfigInterface::class)->andReturn(new Config([
            'dubbo' => [
                'default' => [
                    'driver' => \Yunhu\DubboClient\Driver\ZookeeperDriver::class,
                    'timeout' => 0.0,
                    'pool' => [
                        'min_connections' => 1,
                        'max_connections' => 30,
                        'connect_timeout' => 10.0,
                        'wait_timeout' => 3.0,
                        'heartbeat' => -1,
                        'max_idle_time' => 1,
                    ],
                ],
            ],
        ]));

        return new DubboPoolStub($container, 'default');
    }
}
