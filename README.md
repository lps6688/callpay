# callpay
yii2-pay-sdk
# Alipay.php
集成了支付宝的所有支付接口，但是只实现的支付接口的对接，具体的业务实现还需开发者自己完善。
# Wechat.php
由于测试受限，仅实现的微信支付的部分接口，后续将进行完善
# 使用说明
在main.php中components数组内加入如下代码
```
'alipay' => [
			'class' => 'callpay\pay\sdk\Alipay',
			'app_id' => '**',
			'rsa_private_key' => '****',
			'alipay_public_key' => '***',
		],
```

extensions.php中加入如下代码
```
'callpay/yii2-pay-sdk' =>
		array (
				'name' => 'callpay/yii2-pay-sdk',
				'version' => '1.0.0',
				'alias' =>
				array (
						'@callpay/pay/sdk' => $vendorDir . '/callpay/yii2-pay-sdk',
				),
		),
```


Yii::$app->alipay->barPay($bizParams);快速使用吧
