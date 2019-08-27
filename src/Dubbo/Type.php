<?php
/**
 * This file is part of MyZiyue.
 *
 * @link     https://www.myziyue.com/
 * @author   Bi Zhiming <Evan Bi>
 * @contact  evan2884@gmail.com
 * @license  https://github.com/yunhu/yunhu-zookeeper/blob/master/LICENSE
 */

namespace Myziyue\DubboClient\Dubbo;

use Myziyue\DubboClient\Exception\ConsumerException;

class Type
{
    const ARRAYLIST = 9;
    const DEFAULT_TYPE = 10;

    const adapter = [
        Type::ARRAYLIST => 'Ljava/util/ArrayList;',
        Type::DEFAULT_TYPE => 'Ljava/lang/Object;'
    ];

    /**
     *
     * @param integer $value
     * @return UniversalObject
     */
    public static function object($class, $properties)
    {
        $typeObj = new self();
        $typeObj->className = $class;
        $std = new \stdClass;
        foreach ($properties as $key => $value) {
            $std->$key = $value;
        }
        $typeObj->object = $std;
        return $typeObj;
    }

    /**
     *
     * @param mixed $arg
     * @return string
     * @throws ConsumerException
     */
    public static function argToType($arg)
    {
        $type = gettype($arg);
        switch ($type) {
            case 'integer':
            case 'boolean':
            case 'double':
            case 'string':
            case 'NULL':
                return self::adapter[Type::DEFAULT_TYPE];
            case 'array':
                if (Collection::isSequential($arg)) {
                    return self::adapter[Type::ARRAYLIST];
                } else {
                    return self::adapter[Type::DEFAULT_TYPE];
                }
            case 'object':
                if ($arg instanceof Type) {
                    $className = $arg->className;
                } else {
                    $className = get_class($arg);
                }
                return 'L' . str_replace(['.', '\\'], '/', $className) . ';';
            default:
                throw new ConsumerException("Handler for type {$type} not implemented");
        }
    }

}