<?php

declare(strict_types=1);
/**
 * This file is part of Yunhu.
 *
 * @link     https://www.yunhuyj.com/
 * @contact  zhiming.bi@yunhuyj.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */


namespace YunhuTest\DubboClient\Stub;

use Hyperf\Contract\ConnectionInterface;
use Yunhu\DubboClient\Pool\DubboPool;

class DubboPoolStub extends DubboPool
{
    protected function createConnection(): ConnectionInterface
    {
        return new DubboConnectionStub($this->container, $this, $this->config);
    }
}
