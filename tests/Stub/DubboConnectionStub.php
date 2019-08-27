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

use Myziyue\DubboClient\DubboConnection;

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
