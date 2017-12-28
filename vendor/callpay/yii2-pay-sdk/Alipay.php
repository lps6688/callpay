<?php
namespace callpay\pay\sdk;

use Yii;
use yii\base\Component;

class Alipay extends Component{
	/**
	 * 公共请求参数
	 */
	public $app_id = '';
	public $rsa_private_key = '';
	public $alipay_public_key = '';
	public $notify_url = 'http://www.yiibook.top';
	public $return_url = 'http://www.yiibook.top';
	private $method = '';
	private $format = 'JSON';
	private $charset = 'UTF-8';
	private $sign_type = 'RSA2';
	private $version = '1.0';
	
	private $sysParams = [];
	private $bizParams = [];
	
	const GATEWAY_URL = 'https://openapi.alipaydev.com/gateway.do';
	
	/**
	 * 参数初始化
	 */
	public function initParams(){
		$this->sysParams['app_id'] = $this->app_id;
		$this->sysParams['method'] = $this->method;
		$this->sysParams['format'] = $this->format;
		$this->sysParams['charset'] = $this->charset;
		$this->sysParams['sign_type'] = $this->sign_type;
		$this->sysParams['timestamp'] = date('Y-m-d H:i:s');
		$this->sysParams['version'] = $this->version;
		if($this->notify_url){
			$this->sysParams['notify_url'] = $this->notify_url;
		}
		if($this->return_url){
			$this->sysParams['return_url'] = $this->return_url;
		}
		$this->sysParams['biz_content'] = json_encode($this->bizParams);
		$this->sysParams['sign'] = $this->rsaSign($this->sysParams , $this->sign_type);
	}
	
	/**
	 * RSA2签名
	 */
	protected function rsaSign($params, $signType = 'RSA2'){
		ksort($params);
		$sign_content = '';
		foreach ($params as $k => $v) {
			if (isset($v))
				$sign_content .= $k . '=' . $v . '&';
		}
		$sign_content = substr($sign_content, 0, -1);
		$key = "-----BEGIN RSA PRIVATE KEY-----\n" .
				wordwrap($this->rsa_private_key , 64 , "\n" , true) .
				"\n-----END RSA PRIVATE KEY-----";
		$res = openssl_pkey_get_private($key);
		if (empty($res)) throw new InvalidParamException('Bad private key');
		if ('RSA2' == $signType) {
			openssl_sign($sign_content, $sign, $res, OPENSSL_ALGO_SHA256);
		} else {
			openssl_sign($sign_content, $sign, $res);
		}
		openssl_free_key($res);
		$sign = base64_encode($sign);
		return $sign;
	}
	
	/**
	 * 数据验证
	 */
	protected function rsaVerify($params, $sign , $signType = 'RSA2'){
		$alipay_public_key = "-----BEGIN PUBLIC KEY-----\n" .
				wordwrap($this->alipay_public_key , 64 , "\n" , true) .
				"\n-----END PUBLIC KEY-----";
		ksort($params);
		$sign_content = '';
		foreach ($params as $k => $v) {
			if (isset($v))
				$sign_content .= $k . '=' . $v . '&';
		}
		$data = substr($sign_content, 0, -1);
		$res = openssl_get_publickey($alipay_public_key);
		if ('RSA2' == $signType){
			$result = (bool)openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
		}else{
			$result = (bool)openssl_verify($data, base64_decode($sign), $res);
		}
		openssl_free_key($res);
		return $result;
	}
	
	/**
	 * 
	 */
	protected function curl($url, $postFields = null) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$postBodyString = "";
		$encodeArray = Array();
		$postMultipart = false;
		if (is_array($postFields) && 0 < count($postFields)) {
			foreach ($postFields as $k => $v) {
				$postBodyString .= "$k=" . urlencode($v) . "&";
				$encodeArray[$k] = $v;
			}
			unset ($k, $v);
			curl_setopt($ch, CURLOPT_POST, true);
			if ($postMultipart) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $encodeArray);
			} else {
				curl_setopt($ch, CURLOPT_POSTFIELDS, substr($postBodyString, 0, -1));
			}
		}
		$headers = array('content-type: application/x-www-form-urlencoded;charset=UTF-8');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$reponse = curl_exec($ch);
		if (curl_errno($ch)) {
			throw new Exception(curl_error($ch), 0);
		} else {
			$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if (200 !== $httpStatusCode) {
				throw new Exception($reponse, $httpStatusCode);
			}
		}
		curl_close($ch);
		return $reponse;
	}
	/**
	 * 当面付 条码支付
	 */
	public function barPay($bizParams){
		$this->method = 'alipay.trade.pay';
		$this->bizParams = $bizParams;
		$this->initParams();
		$result = $this->curl(self::GATEWAY_URL , $this->sysParams);
		$result = json_decode($result , true);
		$response = $result['alipay_trade_pay_response'];
		if($response['code'] == 10000){
			return true;
		}elseif($response['code'] == 10003){
			//等待支付
		}
		return false;
	}
	
	/**
	 * 当面付 扫码支付
	 */
	public function qrPay($bizParams){
		$this->method = 'alipay.trade.precreate';
		$this->bizParams = $bizParams;
		$this->initParams();
		$result = $this->curl(static::GATEWAY_URL , $this->sysParams);
		$result = json_decode($result , true);
		$response = $result['alipay_trade_precreate_response'];
		if($response['code'] == 10000){
			return $response['qr_code'];
		}
	}
	
	/**
	 * APP支付 生成签名数据
	 */
	public function appPay($bizParams){
		$bizParams['product_code'] = 'QUICK_MSECURITY_PAY';
		$this->method = 'alipay.trade.app.pay';
		$this->bizParams = $bizParams;
		$this->initParams();
		$result = '';
		foreach ($this->sysParams as $k => $v) {
			if (isset($v))
				$result .= $k . '=' . urlencode($v) . '&';
		}
		$result = substr($result, 0, -1);
		return $result;
	}
	
	/**
	 * WAP支付 生成支付链接
	 */
	public function wapPay($bizParams){
		$bizParams['product_code'] = 'QUICK_WAP_WAY';
		$this->method = 'alipay.trade.wap.pay';
		$this->bizParams = $bizParams;
		$this->initParams();
		$result = '';
		foreach ($this->sysParams as $k => $v) {
			if (isset($v))
				$result .= $k . '=' . urlencode($v) . '&';
		}
		$result = substr($result, 0, -1);
		return static::GATEWAY_URL . '?' . $result;
	}
	
	/**
	 * PC支付 
	 */
	public function pcPay($bizParams){
		$bizParams['product_code'] = 'FAST_INSTANT_TRADE_PAY';
		$this->method = 'alipay.trade.page.pay';
		$this->bizParams = $bizParams;
		$this->initParams();
		$result = '';
		foreach ($this->sysParams as $k => $v) {
			if (isset($v))
				$result .= $k . '=' . urlencode($v) . '&';
		}
		$result = substr($result, 0, -1);
		return static::GATEWAY_URL . '?' . $result;
	}
}