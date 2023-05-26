<?php

declare(strict_types=1);
/**
 * DemoController
 * 
 * @link     https://www.myziyue.com/
 * @contact  evan2884@gmail.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */

namespace App\Controller;

use App\Utils\App;
use Myziyue\DubboClient\Pool\PoolFactory;

class DemoController extends Controller
{
    /**
     * @var ResponseInterface
     */
    protected $response;

    public function index()
    {
        $serverName = "com.myziyue.Demo.HelloService";
    
        $dubboClient = App::getContainer()->get(PoolFactory::class)->getPool("default");

        // 获取服务并调用服务提供的方法hello
        $helloService = $dubboClient->get($serverName);
        $result = $helloService->hello("world");

        return $this->response->json([
            'code' => 0,
            'message' => 'success',
            'data' => $result
        ]);
    }
}
