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


interface DriverInterface
{
    /**
     * 获取服务提供者信息
     *
     * @param string $service
     * @param string $version
     * @param string $group
     * @return array
     */
    public function getProviders(string $service, string $version, string $group):array;
}