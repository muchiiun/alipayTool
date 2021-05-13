<?php
/**
 * Created by PhpStorm.
 * User: icharle
 * Date: 2019/3/10
 * Time: 下午2:24
 */

namespace Muchiiu\Alipaytool;

class Alipaytool
{
    /**
     * 网关
     */
    const GATEWAYURL = 'https://openapi.alipay.com/gateway.do?';

    /**
     * SDK 版本
     */
    const SDK_VERSION = 'alipay-sdk-php-20180705';

    /**
     * API 版本
     */
    const API_VERSION = '1.0';

    /**
     * 返回数据格式
     */
    const FORMAT = 'json';

    /**
     * 表单提交字符集编码
     */
    const CHARSET_UTF8 = 'UTF-8';

    /**
     * 签名类型
     */
    const SIGNTYPE_RSA = 'RSA';

    const SIGNTYPE_RSA2 = 'RSA2';

    const FILECHARSET = 'UTF-8';

    /**
     * 换取授权访问令牌
     */
    const API_METHOD_AUTH_TOKEN = 'alipay.system.oauth.token';

    /**
     * 换取授权访问令牌返回
     */
    const API_METHOD_AUTH_TOKEN_RESPONSE = 'alipay_system_oauth_token_response';

    /**
     * 支付宝会员授权信息查询接口
     */
    const API_METHOD_GET_USER_INFO = 'alipay.user.info.share';

    /**
     * 支付宝会员授权信息查询接口返回
     */
    const API_METHOD_GET_USER_INFO_RESPONSE = 'alipay_user_info_share_response';

    /**
     * 线上资金授权冻结接口返回
     */
    const API_METHOD_FUND_AUTH_ORDER_APP_FREEZE_RESPONSE="alipay_fund_auth_order_app_freeze_response";

    /** 线上资金授权冻结接口
     *  https://opendocs.alipay.com/apis/api_28/alipay.fund.auth.order.app.freeze
     */
    const API_METHOD_FUND_AUTH_ORDER_APP_FREEZE = 'alipay.fund.auth.order.app.freeze';

    /**
     * @var
     * 应用APP_ID  $appId
     */
    private static $appId;

    public function __construct()
    {
        self::$appId = config('alipaytool.ALIPAY_APP_ID');
    }

    /** access_token换取用户信息
     * @param $access_token
     * @return array|mixed
     */
    public static function getUserInfoByAccessToken($access_token)
    {
        $param = self::buildUserInfoParams($access_token);
        $ret = Utils::curl(self::GATEWAYURL . http_build_query($param));
        return self::Resopnse($ret, self::API_METHOD_GET_USER_INFO_RESPONSE);
    }

    /** 根据前端生成的code去换取access_token
     * @param $authCode
     * @return array|mixed
     */
    public static function getAccessToken($authCode)
    {
        $param = self::buildAuthCodeParams($authCode);
        $ret = Utils::curl(self::GATEWAYURL . http_build_query($param));
        return self::Resopnse($ret, self::API_METHOD_AUTH_TOKEN_RESPONSE);
    }

    public static function fundAuthOrderAppFreeze($params)
    {
        $param = self::buildFundAuthOrderAppFreezeParams($params);
        $ret = Utils::curl(self::GATEWAYURL . http_build_query($param));
        return self::Resopnse($ret, self::API_METHOD_FUND_AUTH_ORDER_APP_FREEZE_RESPONSE);
    }

    public static function Resopnse($result, $detailResopnse)
    {
        if (isset($result['error_response'])) {
            return [
                'code' => $result['error_response']['code'],
                'msg' => $result['error_response']['sub_msg']
            ];
        } else {
            return $result[$detailResopnse];
        }
    }

    /** 构建获取用户信息请求业务参数
     * @param $token
     * @return array
     */
    public static function buildUserInfoParams($token)
    {
        $UserInfoParams = [
            'auth_token' => $token,
        ];
        return self::buildSign(static::API_METHOD_GET_USER_INFO, $UserInfoParams);
    }

    /**
     * 构建线上资金授权冻结接口业务参数
     * @param $params
     * @return array
     */
    public static function buildFundAuthOrderAppFreezeParams($params)
    {
        //out_order_no,out_request_no,order_title,amount必填项
        assert(isset($params["out_order_no"]) && $params["out_request_no"] && $params["order_title"] && $params["amount"], "缺少必要参数");
        //销售产品码。新接入线上预授权的业务，支付宝预授权产品固定为 PRE_AUTH_ONLINE；境外预授权产品固定为 OVERSEAS_INSTORE_AUTH 。
        $params["product_code"] = "PRE_AUTH_ONLINE";
        return self::buildSign(static::API_METHOD_FUND_AUTH_ORDER_APP_FREEZE, $params);
    }

    /** 构建获取用户授权code请求业务参数
     * @param $code
     * @param string $refreshToken
     * @return array
     */
    public static function buildAuthCodeParams($code, $refreshToken = '')
    {
        $AuthCodeParams = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'refresh_token' => $refreshToken,
        ];
        $Param = self::buildSign(static::API_METHOD_AUTH_TOKEN, $AuthCodeParams);
        return $Param;
    }

    /** 构建公共参数
     * @param $method string (API_METHOD_AUTH_TOKEN、API_METHOD_GET_USER_INFO)
     * @return mixed $Params 公共参数
     */
    public static function buildCommonParams($method)
    {
        $commonParams["app_id"] = static::$appId;
        $commonParams["method"] = $method;
        $commonParams["format"] = static::FORMAT;
        $commonParams["charset"] = static::CHARSET_UTF8;
        $commonParams["sign_type"] = static::SIGNTYPE_RSA2;
        $commonParams["timestamp"] = date("Y-m-d H:i:s");
        $commonParams["version"] = static::API_VERSION;

        return $commonParams;
    }

    /**
     * 签名生成sign值
     * @param $apiMethod string 接口名称
     * @param $businessParams array  业务特殊参数
     * @return array
     */
    public static function buildSign($apiMethod, $businessParams)
    {
        //构建公共参数
        $pubParam = self::buildCommonParams($apiMethod);
        //构建业务参数
        $businessParams = array_merge($pubParam, $businessParams);
        $signContent = self::getSignContent($businessParams);
        $sign = (new Rsasign())::generateSignature($signContent);
        $businessParams['sign'] = $sign;
        return $businessParams;
    }

    /**
     * 筛选并排序&&拼接
     * @param $params array 所有参数
     * @return string 待签名字符串
     * @see https://docs.open.alipay.com/291/106118 自行实现签名
     */
    public static function getSignContent($params)
    {
        ksort($params);

        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === self::checkEmpty($v) && "@" != substr($v, 0, 1)) {

                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }

    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *  if is null , return true;
     **/
    public static function checkEmpty($value)
    {
        return $value === null || trim($value) === '';
    }


}