<?php

declare(strict_types=1);
/**
 * This file is part of Yunhu.
 *
 * @link     https://www.yunhuyj.com/
 * @contact  zhiming.bi@yunhuyj.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */

namespace Yunhu\DubboClient;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ConnectionInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Pool\Connection as BaseConnection;
use Hyperf\Pool\Pool;
use Psr\Container\ContainerInterface;
use Yunhu\DubboClient\Driver\DriverInterface;
use Yunhu\DubboClient\Dubbo\DubboRequest;
use Yunhu\DubboClient\Dubbo\Processor;
use Yunhu\DubboClient\Dubbo\Type;
use Yunhu\DubboClient\Exception\ConsumerException;
use Yunhu\DubboClient\Exception\DubboServerException;
use Yunhu\DubboClient\Utils\Utils;

class DubboConnection extends BaseConnection implements ConnectionInterface
{
    /**
     * @var DubboRequest
     */
    protected $connection;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $config = [
        'timeout' => 3
    ];

    /**
     * @var DriverInterface
     */
    protected $dirver;

    /**
     * @var Processor
     */
    protected $processor;

    /**
     * @var string
     */
    protected $appGroup = 'default';
    /**
     * @var string
     */
    protected $appServices;
    /**
     * @var string
     */
    protected $appVersion = '1.0.0';

    public function __construct(ContainerInterface $container, Pool $pool, array $config)
    {
        parent::__construct($container, $pool);
        $this->config = array_replace($this->config, $config);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->dirver = (new DubboManager($container->get(ConfigInterface::class), $this->logger))->getDriver();

        $this->reconnect();
    }

    public function getActiveConnection()
    {
        if ($this->check()) {
            return $this;
        }
        $this->reconnect();

        return $this;
    }

    public function reconnect(): bool
    {
        try {
            $dubboRequest = DubboRequest::getInstance();
            $processor = Processor::getInstance();
        } catch (\Exception $ex) {
            throw new DubboServerException("Connection reconnect failed : {$ex->getMessage()} | {$this->zkHosts}");
        }

        $this->connection = $dubboRequest;
        $this->processor = $processor;
        $this->lastUseTime = microtime(true);

        return true;
    }

    public function __call($name, $args)
    {
        $this->vaildParams();

        $result = NULL;
        $method = null;
        $providerAddress = NULL;
        //取到微秒
        $begin_time = microtime(true);
        $this->logger->debug("in|consumer_app:{$this->appGroup}|service:{$this->appServices}|timout:{$this->config['timeout']}|name:{$name}");
        try {
            $this->connection->setSn(Utils::generatePackageSN());
            $this->connection->setService($this->appServices);
            $this->connection->setMethod($args[0]);
            array_shift($args);
            $this->connection->setTypes($this->generateParamType($args));
            $this->connection->setParams($args);
            $result = $this->processor->executeRequest($this->connection,
                $this->dirver->getProviders($this->appServices, $this->appVersion, $this->appGroup),
                $this->config['timeout'],$providerAddress);
        } catch (\Exception $e) {
            $costTime = (int)((microtime(true) - $begin_time) * 1000000);
            //记录consumer接口告警日志
            $this->setAccLog($this->connection, $costTime, $e->getMessage());
            throw new ConsumerException($e->getMessage());
        }
        $costTime = (int)((microtime(true) - $begin_time) * 1000000);
        //记录consumer接口告警日志
        $this->setAccLog($this->connection, $costTime, "ok");
        return $result;
    }

    protected function setAccLog($request, $costTime, $errMsg = 'ok')
    {
        //时间|服务名|耗时（us）|返回码|应用名|方法名|目标服务group|目标服务version|目标机器ip:port|备注
        $accLog = sprintf("Serivce: %s|Cost Time:%dμs|App Name:%d|Method:%s|Group:%s|Version:%s|Target IP:%s|Msg:%s", $request->getService(), $costTime,
            $this->appGroup,
            $request->getMethod(),
            $request->getGroup(),
            $request->getVersion(),
            $request->host . ':' . $request->port,
            $errMsg);
        $this->logger->debug($accLog);
    }

    protected function generateParamType($args)
    {
        $types = [];
        foreach ($args as $val) {
            $types[] = Type::argToType($val);
        }
        return $types;
    }

    /**
     * Close the connection.
     */
    public function close(): bool
    {
        return true;
    }

    protected function vaildParams(){
        if (empty($this->appGroup)) {
            throw new DubboServerException("Application Group is not set, please call 'setAppGroup()' to set.");
        }

        if (empty($this->appVersion)) {
            throw new DubboServerException("Application Version is not set, please call 'setAppVersion()' to set.");
        }

        if (empty($this->appServices)) {
            throw new DubboServerException("Application Services is not set, please call 'setAppServices()' to set.");
        }
    }

    /**
     * @param string $appGroup
     */
    public function setAppGroup(string $appGroup): void
    {
        $this->appGroup = $appGroup;
    }

    /**
     * @param string $appServices
     */
    public function setAppServices(string $appServices): void
    {
        $this->appServices = $appServices;
    }

    /**
     * @param string $appVersion
     */
    public function setAppVersion(string $appVersion): void
    {
        $this->appVersion = $appVersion;
    }


}