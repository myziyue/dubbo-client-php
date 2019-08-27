<?php

declare(strict_types=1);
/**
 * This file is part of MyZiyue.
 *
 * @link     https://www.myziyue.com/
 * @contact  evan2884@gmail.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */


namespace MyziyueTest\DubboClient\Stub;

use Hyperf\Contract\ConnectionInterface;
use Myziyue\DubboClient\Pool\DubboPool;

class DubboPoolStub extends DubboPool
{
    protected function createConnection(): ConnectionInterface
    {
        return new DubboConnectionStub($this->container, $this, $this->config);
    }
}
