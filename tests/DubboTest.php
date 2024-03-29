<?php

declare(strict_types=1);
/**
 * This file is part of MyZiyue.
 *
 * @link     https://www.myziyue.com/
 * @contact  evan2884@gmail.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */


namespace MyziyueTest\DubboClient;

use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Container;
use Hyperf\Utils\ApplicationContext;
use Mockery;
use PHPUnit\Framework\TestCase;
use Myziyue\DubboClient\Dubbo;
use Myziyue\DubboClient\DubboConnection;
use Myziyue\DubboClient\Pool\DubboPool;
use Myziyue\DubboClient\Pool\PoolFactory;
use MyziyueTest\DubboClient\Stub\DubboPoolStub;

/**
 * @internal
 * @coversNothing
 */
class DubboTest extends TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function testRedisConnect()
    {
        $dubbo = new DubboConnection();
        $this->assertTrue($dubbo);

        $class = new \ReflectionClass($dubbo);
        $params = $class->getMethod('connect')->getParameters();
        [$driver, $timeout, $retryInterval] = $params;
        $this->assertSame('driver', $driver->getName());
        $this->assertSame('timeout', $timeout->getName());
        $this->assertSame('retry_interval', $retryInterval->getName());
    }

    public function testDubboSelect()
    {
        $dubbo = $this->getDubbo();

        $res = $dubbo->set('\xxxx', 'yyyy');
        $this->assertSame('name:set argument:\xxxx,yyyy', $res);

        $res = $dubbo->get('\xxxx');
        $this->assertSame('name:get argument:\xxxx', $res);

        $res = parallel([function () use ($dubbo) {
            return $dubbo->get('\xxxx');
        }]);

        $this->assertSame('name:get argument:\xxxx', $res[0]);
    }

    private function getDubbo()
    {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('get')->once()->with(ConfigInterface::class)->andReturn(new Config([
            'redis' => [
                'default' => [
                    'driver' => \Myziyue\DubboClient\Driver\ZookeeperDriver::class,
                    'timeout' => 0.0,
                    'pool' => [
                        'min_connections' => 1,
                        'max_connections' => 30,
                        'connect_timeout' => 10.0,
                        'wait_timeout' => 3.0,
                        'heartbeat' => -1,
                        'max_idle_time' => 60,
                    ],
                ],
            ],
        ]));
        $pool = new DubboPoolStub($container, 'default');
        $container->shouldReceive('make')->once()->with(DubboPool::class, ['name' => 'default'])->andReturn($pool);

        ApplicationContext::setContainer($container);

        $factory = new PoolFactory($container);

        return new Dubbo($factory);
    }
}
