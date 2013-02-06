yii-platron
=======

Yii-расширение для работы с api сервиса [platron.ru](http://platron.ru)

## Установка

Загрузите yii-platron из этого репозитория github:

    cd protected/extensions
    git clone git://github.com/pimax/yii-platron.git

В protected/config/main.php внесите следующие строки:

    'components' => array
    (
        'platron' => array(
            'class' => 'application.extensions.yii-paltron.PlatronPayment',
            'merchant_id' => '12345',
            'secret_key' => 'secret_key',
            'site_url' => 'http://site.ru',
            'result_url' => 'http://site.ru/shop/default/result',
            'success_url' => 'http://site.ru/shop/default/success',
            'failure_url' => 'http://site.ru/shop/default/failed',
            'test_mode' => '1',
            'request_method' => 'POST'
        )
    );

## Использование

Инициализация оплаты:

    public function actionPay()
    {
        $oOrder = new ShopOrder();
        $oOrder->amount = 5600;
        $oOrder->description = "Тестовый платеж";
        $oOrder->save();
        
        $result = Yii::app()->platron->getUrlForPayment($oOrder->id, $oOrder->amount, $oOrder->description);
        
        $this->redirect($result);
    }

Result URL:

    public function actionResult()
    {
        $pg_order_id = !empty($_REQUEST["pg_order_id"]) ? intval($_REQUEST["pg_order_id"]) : 0;
        $oOrder = ShopOrder::model()->find(array('condition' => "id = :id ", 'params' => array(':id' => $pg_order_id)));
        
        if (Yii::app()->platron->checkPayment($oOrder))
        {
            // payment success
            if ($oOrder->pay_success != 1)
            {
                $oOrder->pay_success = 1;
                $oOrder->date_pay = date("Y-m-d H:i:s");
                $oOrder->save();
            }
        }
    }

Success URL:

    public function actionSuccess()
    {
        $this->render('success');
    }

Failure URL:

    public function actionFailed()
    {
        $this->render('failed');
    }