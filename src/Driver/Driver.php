<?php

declare(strict_types=1);
/**
 * This file is part of Yunhu.
 *
 * @link     https://www.yunhuyj.com/
 * @contact  zhiming.bi@yunhuyj.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */

namespace Yunhu\DubboClient\Driver;


use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Container\ContainerInterface;
use Yunhu\DubboClient\Constants;
use Yunhu\DubboClient\Dubbo\Url;

abstract class Driver
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container, array $config)
    {
        $this->container = $container;
        $this->config = $config;
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    protected function getPrividersKey(string $service)
    {
        return '/dubbo/' . $service . '/providers';
    }

    protected function getProviderUrls($providerInfo, $version, $group, $service)
    {
        $urls = [];
        if (is_array($providerInfo)) {
            foreach ($providerInfo as $index => $url) {
                try {
                    $url = urldecode($url);
                    $urlObj = new Url($url);
                    if (!empty($urlObj)) {
                        if (0 == strncmp($urlObj->getService(), $service, strlen($service))
                            && $version == $urlObj->getVersion(Constants::YH_DUBBO_SERVICE_VERSION_DEFAULT)
                            && ($group == Constants::YH_DUBBO_SERVICE_GROUP_ANY
                                || $group == $urlObj->getGroup(Constants::YH_DUBBO_SERVICE_GROUP_ANY))) {
                            $urls[] = $urlObj;
                            $this->logger->debug("Find provider for [{$service}:{$version}:{$group}] | url: " .$url);
                        }
                    } else {
                        $this->logger->error("Get data Exception, service: {$service} , data list : " . $url);
                    }
                } catch (\Exception $exception) {
                    $this->logger->error("Error of url:{$url}", $exception);
                }
            }
        }

        if (empty($urls)) {
            $this->logger->warning("version: {$version}, group: {$group}, service : {$service} get provider info is empty");
        }
        return $urls;
    }
}