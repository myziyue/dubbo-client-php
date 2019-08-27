<?php

declare(strict_types=1);
/**
 * This file is part of MyZiyue.
 *
 * @link     https://www.myziyue.com/
 * @contact  evan2884@gmail.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */

namespace Myziyue\DubboClient\Driver;


use Yunhu\YunhuZookeeper\Zookeeper;

class ZookeeperDriver extends Driver implements DriverInterface
{

    /**
     * 获取服务提供者信息
     *
     * @param string $service
     * @param string $version
     * @param string $group
     * @return array
     */
    public function getProviders(string $service, string $version, string $group): array
    {
        $provider = [];
        try {
            $providerInfo = $this->container->get(Zookeeper::class)->getChildren($this->getPrividersKey($service));
            $provider = $this->getProviderUrls($providerInfo, $version, $group, $service);
        } catch (\Exception $exception) {
            $this->container->get(Zookeeper::class)->close();
            $this->logger->error("获取Provider信息异常：{$exception->getMessage()}", $exception);
        }
        return $provider;
    }
}