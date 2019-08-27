<?php

declare(strict_types=1);
/**
 * This file is part of Yunhu.
 *
 * @link     https://www.yunhuyj.com/
 * @contact  zhiming.bi@yunhuyj.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */

namespace Yunhu\DubboClient\Utils;


class Utils
{
    public static function generatePackageSn()
    {
        srand((int)microtime() * 1000000);
        return rand();
    }
}