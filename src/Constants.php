<?php

declare(strict_types=1);
/**
 * This file is part of MyZiyue.
 *
 * @link     https://www.myziyue.com/
 * @contact  evan2884@gmail.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */

namespace Myziyue\DubboClient;


class Constants
{
    //任何服务分组
    const YH_DUBBO_SERVICE_GROUP_ANY = '*';
    //默认服务分组
    const YH_DUBBO_SERVICE_GROUP_DEFAULT = 'default';
    //默认服务版本
    const YH_DUBBO_SERVICE_VERSION_DEFAULT = '1.0.0';

    //monitor定时监控时间，暂定5分钟
    const YH_DUBBO_MONITOR_TIMER = 300000;

    //默认所使用的redis端口号
    const YH_DUBBO_SERVICE_REDIS_PORT =6379;
    //默认所使用的redis地址
    const YH_DUBBO_SERVICE_REDIS_HOST ='127.0.0.1';

    const YH_DUBBO_SERVICE_REDIS_CONNECT_TYPE_TCP = 'TCP';

    const YH_DUBBO_SERVICE_REDIS_CONNECT_TYPE_SOCK = 'SOCK';

    // Dubbo 服务提供者信息来源
    const YH_DUBBO_PROVIDER_REDIS = 1;
    const YH_DUBBO_PROVIDER_ZK = 2;

}