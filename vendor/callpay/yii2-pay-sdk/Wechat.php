<?php
namespace callpay\pay\sdk;

use Yii;
use yii\base\Component;

class Wechat extends Component{
	public $appid = '';
	public $mch_id = '';
	public $apikey = '';
	public $appsecret = '';
	
	public $sslcertPath = '';
	public $sslkeyPath = '';
	public $notify_url = '';
	
	const URL_UNIFIEDORDER = "https://api.mch.weixin.qq.com/pay/unifiedorder";
	const URL_MICROPAY = 'https://api.mch.weixin.qq.com/pay/micropay';
	
	/**
	 * 获得随机串
	 */
	private function get_nonce_string(){
		return substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 32);
	}
	
	/**
	 * 数据签名
	 */
	private function sign($data){
		ksort($data);
		$string1 = "";
		foreach ($data as $k => $v) {
			if ($v && trim($v) != '') {
				$string1 .= "$k=$v&";
			}
		}
		$stringSignTemp = $string1 . "key=" . $this->apikey;
		$sign = strtoupper(md5($stringSignTemp));
		return $sign;
	}
	
	private function array2xml($array){
		$xml = "<xml>" . PHP_EOL;
		foreach ($array as $k => $v) {
			if ($v && trim($v) != '')
				$xml .= "<$k><![CDATA[$v]]></$k>" . PHP_EOL;
		}
		$xml .= "</xml>";
		return $xml;
	}
	
	private function xml2array($xml){
		$array = array();
		$tmp = null;
		try {
			$tmp = (array)simplexml_load_string($xml);
		} catch (Exception $e) {
		}
		if ($tmp && is_array($tmp)) {
			foreach ($tmp as $k => $v) {
				$array[$k] = (string)$v;
			}
		}
		return $array;
	}
	
	private function post($url, $data, $cert = false){
		if (!isset($data['sign'])) $data['sign'] = $this->sign($data);
		$xml = $this->array2xml($data);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		if ($cert == true) {
			//使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
			curl_setopt($ch, CURLOPT_SSLCERT, $this->sslcertPath);
			curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
			curl_setopt($ch, CURLOPT_SSLKEY, $this->sslkeyPath);
		}
		$content = curl_exec($ch);
		$array = $this->xml2array($content);
		return $array;
	}
	
	/**
	 * 统一下单
	 */
	private function unifiedOrder($params){
		$data = [];
		$data["appid"] = $this->appid;
		$data["mch_id"] = $this->mch_id;
		$data["device_info"] = (isset($params['device_info']) && trim($params['device_info']) != '') ? $params['device_info'] : null;
		$data["nonce_str"] = $this->get_nonce_string();
		$data["body"] = $params['body'];
		$data["detail"] = isset($params['detail']) ? $params['detail'] : null;//optional
		$data["attach"] = isset($params['attach']) ? $params['attach'] : null;//optional
		$data["out_trade_no"] = isset($params['out_trade_no']) ? $params['out_trade_no'] : null;
		$data["fee_type"] = isset($params['fee_type']) ? $params['fee_type'] : 'CNY';
		$data["total_fee"] = $params['total_fee'];
		$data["spbill_create_ip"] = $_SERVER["REMOTE_ADDR"];
		$data["time_start"] = isset($params['time_start']) ? $params['time_start'] : null;//optional
		$data["time_expire"] = isset($params['time_expire']) ? $params['time_expire'] : null;//optional
		$data["goods_tag"] = isset($params['goods_tag']) ? $params['goods_tag'] : null;
		$data["notify_url"] = $this->notify_url;
		$data["trade_type"] = $params['trade_type'];
		$data["product_id"] = isset($params['product_id']) ? $params['product_id'] : null;//required when trade_type = NATIVE
		$data["openid"] = isset($params['openid']) ? $params['openid'] : null;//required when trade_type = JSAPI
		$data["scene_info"] = isset($params['scene_info']) ? $params['scene_info'] : null;
		$result = $this->post(self::URL_UNIFIEDORDER, $data);
		return $result;
	}
	
	/**
	 * 刷卡支付
	 */
	public function microPay($bizParams){
		$data = [];
		$data["appid"] = $this->appid;
		$data["mch_id"] = $this->mch_id;
		$data["nonce_str"] = $this->get_nonce_string();
		$data["body"] = $bizParams['body'];
		$data["out_trade_no"] = $bizParams['out_trade_no'];
		$data["total_fee"] = $bizParams['total_fee'];
		$data["spbill_create_ip"] = $_SERVER["REMOTE_ADDR"];
		$data["auth_code"] = $bizParams['auth_code'];
		$result = $this->post(self::URL_MICROPAY , $data);
		print_r($result);exit;
	}
	
	/**
	 * H5支付
	 */
	public function h5Pay($bizParams){
		$bizParams['trade_type'] = 'MWEB';
		print_r($this->unifiedOrder($bizParams));
	}
	
	/**
	 * 扫码支付
	 */
	public function qrPay($bizParams){
		$bizParams['trade_type'] = 'NATIVE';
		$result = $this->unifiedOrder($bizParams);
		if ($result["return_code"] == "SUCCESS" && $result["result_code"] == "SUCCESS") {
			return $result["code_url"];
		} else {
			$this->error = $result["return_code"] == "SUCCESS" ? $result["err_code_des"] : $result["return_msg"];
			return null;
		}
	}
	
}