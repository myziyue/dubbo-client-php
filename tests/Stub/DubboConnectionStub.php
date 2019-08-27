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

use Yunhu\DubboClient\DubboConnection;

class DubboConnectionStub extends DubboConnection
{
    public $dirver;

    public $timeout;

    public function __call($name, $arguments)
    {
        return sprintf('Driver:%s argument:%s', $this->dirver, implode(',', $arguments));
    }

    public function reconnect(): bool
    {
        $this->dirver = $this->config['driver'];
        $this->timeout = $this->config['timeout'];

        return true;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
