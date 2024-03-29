<?php

namespace Myziyue\DubboClient\Dubbo;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Utils\ApplicationContext;
use Icecave\Flax\Serialization\Encoder;
use Icecave\Flax\DubboParser as Decoder;
use Myziyue\DubboClient\Exception\ConsumerException;

/**
 *
 * Dubbo网络协议
 * +------------------------------------------------------------------------------------------+
 * |                        包头（二进制数据 16bit）   |  包体  |
 * +-----------------------------------------------------------------------------------------+
 * | 版本号  |   命令&serialize   | 空白 | 包序号|  长度  |  数据  |
 * +-----------------------------------------------------------------------------------------+
 * | magic(2) |  cmd&serialize(1)|(1)    |sn(8) | len(4)  | data(N)|
 * +-----------------------------------------------------------------------------------------+
 *
 * magic：协议包起始标识, 0xdabb
 * --------------------------------------------------------------------------------------------
 * cmd：命令类型：FLAG_REQUEST为0x80, FLAG_TWOWAY为0x40, FLAG_EVENT为0x20
 * serialize：序列化方案编号：与cmd共用一个字节,采用json,对应dubbo中编号为6
 * --------------------------------------------------------------------------------------------
 * sn：请求序号，consumer会为每个请求编制一个进程内唯一序号
 *    ，provider处理完请求后在返回的数据包中会携带该sn号，供consumer判断当前的数据是对应哪个请求
 * --------------------------------------------------------------------------------------------*
 * len：数据报文长度
 * --------------------------------------------------------------------------------------------
 * data：数据报文，目前采用json进行序列化
 * --------------------------------------------------------------------------------------------
 */
class DubboParser
{
    //dubbo协议基本信息
    const PACKAGE_HEDA_LEN = 16;
    const MAX_RECV_LEN = 1048576;//1024*1024;
    const RESPONSE_TCP_SEGMENT_LEN = 1048576;//1*1024*1024;

    //Dubbo协议ver字段，ver字段既指示协议版本信息，也作为magic使用
    const DUBBO_PROTOCOL_MAGIC = 0xdabb;

    //serialize 方案编号
    const DUBBO_PROTOCOL_SERIALIZE_FAST_JSON = 6;     //fastjson serialization code

    const DUBBO_PROTOCOL_SERIALIZE_HESSIAN2 = 2;     //hessian2 serialization code

    const DUBBO_PROTOCOL_NAME_MAP_CODE = [
        'hessian2' => self::DUBBO_PROTOCOL_SERIALIZE_HESSIAN2,
        'fastjson' => self::DUBBO_PROTOCOL_SERIALIZE_FAST_JSON
    ];


    //Dubbo协议包头cmd字段含义
    const FLAG_REQUEST = 0x80;           //request
    const FLAG_TWOWAY = 0x40;            //two_way
    const FLAG_HEARTBEAT_EVENT = 0x20;  //heart_event
    const SERIALIZATION_MASK = 0x1f;     //serialization_mask

    const UPPER_MASK = 0xffffffff00000000;
    const LOWER_MASK = 0x00000000ffffffff;

    const RESPONSE_WITH_EXCEPTION = 0;
    const RESPONSE_VALUE = 1;
    const RESPONSE_NULL_VALUE = 2;


    public function packRequest(DubboRequest $request)
    {
        if (self::DUBBO_PROTOCOL_SERIALIZE_HESSIAN2 == (self::DUBBO_PROTOCOL_NAME_MAP_CODE[$request->getSerialization()] ?? null)) {
            $reqData = $this->buildBodyForHessian2($request);
            $serialize_type = self::DUBBO_PROTOCOL_SERIALIZE_HESSIAN2;
        } else {
            $reqData = $this->buildBodyForFastJson($request);
            $serialize_type = self::DUBBO_PROTOCOL_SERIALIZE_FAST_JSON;
        }
        $upper = ($request->getSn() & self::UPPER_MASK) >> 32;
        $lower = $request->getSn() & self::LOWER_MASK;
        $flag = (self::FLAG_REQUEST | $serialize_type);
        if ($request->isTwoWay()) $flag |= self::FLAG_TWOWAY;
        if ($request->isHeartbeatEvent()) $flag |= self::FLAG_HEARTBEAT_EVENT;
        $out = pack("n1C1a1N1N1N1",
            self::DUBBO_PROTOCOL_MAGIC,
            $flag,
            "",
            $upper,
            $lower,
            strlen($reqData));
        return $out . $reqData;
    }

    public function buildBodyForFastJson(DubboRequest $request)
    {
        $reqData = json_encode($request->getDubboVersion()) . PHP_EOL .
            json_encode($request->getService()) . PHP_EOL;
        if ($request->getVersion()) {
            $reqData .= json_encode($request->getVersion()) . PHP_EOL;
        } else {
            $reqData .= '""' . PHP_EOL;
        }
        $reqData .= json_encode($request->getMethod()) . PHP_EOL;
        $reqData .= json_encode($this->typeRefs($request)) . PHP_EOL;
        foreach ($request->getParams() as $value) {
            $reqData .= json_encode($value) . PHP_EOL;
        }
        $attach = array();
        $attach['path'] = $request->getService();
        $attach['interface'] = $request->getService();
        if ($request->getGroup()) {
            $attach['group'] = $request->getGroup();
        }
        if ($request->getVersion()) {
            $attach['version'] = $request->getVersion();
        }
        $attach['timeout'] = $request->getTimeout();
        $request->setAttach($attach);
        $reqData .= json_encode($request->getAttach());
        return $reqData;

    }

    public function buildBodyForHessian2(DubboRequest $request)
    {
        $encode = new Encoder();
        $reqData = '';
        $reqData .= $encode->encode($request->getDubboVersion());
        $reqData .= $encode->encode($request->getService());
        if ($request->getVersion()) {
            $reqData .= $encode->encode($request->getVersion());
        } else {
            $reqData .= $encode->encode('');
        }
        $reqData .= $encode->encode($request->getMethod());
        $reqData .= $encode->encode($this->typeRefs($request));
        foreach ($request->getParams() as $value) {
            $reqData .= $encode->encode($value);
        }
        $attach = ['path' => $request->getService(), 'interface' => $request->getService(), 'timeout' => $request->getTimeout()];
        if ($request->getGroup()) {
            $attach['group'] = $request->getGroup();
        }
        if ($request->getVersion()) {
            $attach['version'] = $request->getVersion();
        }
        $reqData .= $encode->encode($attach);
        return $reqData;
    }

    private function typeRefs(DubboRequest $request)
    {
        $typeRefs = '';
        foreach ($request->getTypes() as $type) {
            $typeRefs .= $type;
        }
        return $typeRefs;
    }


    public function parseResponseHeader(DubboResponse $response)
    {
        $res_header = substr($response->getFullData(), 0, self::PACKAGE_HEDA_LEN);
        $format = 'n1magic/C1flag/C1status/N1upper/N1lower/N1len';
        $_arr = unpack($format, $res_header);
        $response->setStatus($_arr['status']);
        $response->setSn($_arr["upper"] << 32 | $_arr["lower"]);
        $flag = $_arr["flag"];
        if (($flag & self::FLAG_HEARTBEAT_EVENT) != 0) {
            $response->setHeartbeatEvent(true);
        }
        $response->setSerialization($flag & self::SERIALIZATION_MASK);
        $response->setLen($_arr["len"]);
        return $response;
    }

    public function parseResponseBody(DubboResponse $response)
    {
        if (DubboResponse::OK == $response->getStatus()) {
            if (self::DUBBO_PROTOCOL_SERIALIZE_FAST_JSON == $response->getSerialization()) {
                $this->parseResponseBodyForFastJson($response);
            } else if (self::DUBBO_PROTOCOL_SERIALIZE_HESSIAN2 == $response->getSerialization()) {
                $this->parseResponseBodyForHessian2($response);
            } else {
                throw new ConsumerException(sprintf('返回的序列化类型:(%s), 不支持解析!', $response->getSerialization()));
            }
        } else {
            throw new ConsumerException($response->getFullData());
        }
        return $response;
    }

    private function parseResponseBodyForFastJson(DubboResponse $response)
    {
        $_data = substr($response->getFullData(), self::PACKAGE_HEDA_LEN);
        $response->setResponseBody($_data);
        list($status, $content) = explode(PHP_EOL, $_data);
        if ($response->isHeartbeatEvent()) {
            $response->setResult(json_decode($status, true));
        } else {
            switch ($status) {
                case self::RESPONSE_NULL_VALUE:
                    break;
                case self::RESPONSE_VALUE:
                    $response->setResult(json_decode($content, true));
                    break;
                case self::RESPONSE_WITH_EXCEPTION:
                    $exception = json_decode($content, true);
                    if (is_array($exception) && array_key_exists('message', $exception)) {
                        throw new ConsumerException($exception['message']);
                    } else if (is_string($exception)) {
                        throw new ConsumerException($exception);
                    } else {
                        throw new ConsumerException("provider occur error");
                    }
                    break;
                default:
                    return false;
            }
        }
        return $response;
    }

    private function parseResponseBodyForHessian2(DubboResponse $response)
    {
        if (!$response->isHeartbeatEvent()) {
            $_data = $response->getFullData();
            $decode = new Decoder($_data);
            $content = $decode->getData($_data);
            $response->setResult($content);
        }
        return $response;
    }


    public function parseRequestHeader(DubboRequest &$request)
    {
        $_data = substr($request->getFullData(), 0, self::PACKAGE_HEDA_LEN);
        $format = 'n1magic/C1flag/C1blank/N1upper/N1lower/N1len';
        $_arr = unpack($format, $_data);
        $flag = $_arr['flag'];
        $request->setTwoWay(($flag & self::FLAG_TWOWAY) != 0);
        if (($flag & self::FLAG_HEARTBEAT_EVENT) != 0) {
            $request->setHeartbeatEvent(true);
        }
        $request->setSerialization($flag & self::DUBBO_PROTOCOL_SERIALIZE_FAST_JSON);
        $request->setSn($_arr['upper'] << 32 | $_arr['lower']);
        $request->setDataLen($_arr['len']);
        $request->setRequestLen($request->getDataLen() + self::PACKAGE_HEDA_LEN);
        return $request;
    }

    public function parseRequestBody(DubboRequest &$request)
    {
        if ($request->getSerialization() != self::DUBBO_PROTOCOL_SERIALIZE_FAST_JSON) {
            ApplicationContext::getContainer()->get(StdoutLoggerInterface::class)->error("unknown serialization type :" . $request->getSerialization());
            return false;
        }
        $cliData = substr($request->getFullData(), self::PACKAGE_HEDA_LEN);
        if ($cliData) {
            if ($request->isHeartbeatEvent()) {
                //心跳请求,不需要数据回送
            } else {
                $dataArr = explode(PHP_EOL, $cliData);
                $request->setDubboVersion(json_decode($dataArr[0], true));
                $request->setService(json_decode($dataArr[1], true));
                $request->setVersion(json_decode($dataArr[2], true));
                $methodName = json_decode($dataArr[3], true);
                if ($methodName == "\$invoke") {
                    //泛化调用
                    $request->setMethod(json_decode($dataArr[5], true));
                    $request->setParams(json_decode($dataArr[7], true));
                    $attach = json_decode($dataArr[8], true);
                } else {
                    //非泛化调用
                    $request->setMethod($methodName);
                    $paramTypes = json_decode($dataArr[4], true);
                    if ($paramTypes == "") {
                        //调用没有参数的方法
                        $request->setTypes(NULL);
                        $request->setParams(NULL);
                        $attach = json_decode($dataArr[5], true);
                    } else {
                        $typeArr = explode(";", $paramTypes);
                        $typeArrLen = count($typeArr);
                        $request->setParamNum($typeArrLen - 1);
                        $params = array();
                        for ($i = 0; $i < $typeArrLen - 1; $i++) {
                            $params[$i] = json_decode($dataArr[5 + $i], true);
                        }
                        $request->setParams($params);
                        $attach = json_decode($dataArr[5 + ($typeArrLen - 1)], true);
                    }
                }
                $request->setAttach($attach);
                if (array_key_exists('group', $attach)) {
                    $request->setGroup($attach['group']);
                }
                return $request;
            }
        }
        return false;
    }


    public function packResponse(DubboResponse &$response)
    {
        if ($response->getStatus() != DubboResponse::OK) {
            $resData = json_encode($response->getErrorMsg());
        } else {
            if ($response->getErrorMsg() != NULL && $response->getErrorMsg() != "") {
                $resData = json_encode(self::RESPONSE_WITH_EXCEPTION) . PHP_EOL . json_encode($response->getErrorMsg());
            } else if ($response->getResult() == NULL) {
                $resData = json_encode(self::RESPONSE_NULL_VALUE);
            } else {
                $resData = json_encode(self::RESPONSE_VALUE) . PHP_EOL . json_encode($response->getResult());
            }
        }
        $resData = $resData . PHP_EOL;
        $upper = ($response->getSn() & self::UPPER_MASK) >> 32;
        $lower = $response->getSn() & self::LOWER_MASK;
        $flag = self::DUBBO_PROTOCOL_SERIALIZE_FAST_JSON;
        if ($response->isHeartbeatEvent()) {
            $flag |= self::FLAG_HEARTBEAT_EVENT;
        }
        $out = pack("n1C1C1N1N1N1",
            self::DUBBO_PROTOCOL_MAGIC,
            $flag,
            $response->getStatus(),
            $upper,
            $lower,
            strlen($resData));

        return $out . $resData;
    }

    public function isNormalResponse(DubboResponse $response)
    {
        return !($response->isHeartbeatEvent());
    }

    public function isNormalRequest(DubboRequest $request)
    {
        return !($request->isHeartbeatEvent());
    }

    public function isOneWayRequest(DubboRequest $request)
    {
        return !($request->isTwoWay());
    }

    public function isHearBeatRequest(DubboRequest $request)
    {
        return $request->isHeartbeatEvent();
    }

    public function isHearBeatResponse(DubboResponse $response)
    {
        return $response->isHeartbeatEvent();
    }

    public static function getReqInQueueTime(DubboRequest $request)
    {
        $ret = 0;
        if (!empty($request->reqInfo)) {
            $ret = isset($request->reqInfo['inqueue_time']) ? $request->reqInfo['inqueue_time'] : 0;
        }
        return $ret;
    }
}