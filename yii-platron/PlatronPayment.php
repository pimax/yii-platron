<?php

class PlatronPayment extends CApplicationComponent
{
    const PAYMENT_URL = 'https://www.platron.ru/payment.php';
    
    public $merchant_id;
    
    public $secret_key;
    
    public $site_url;
    
    public $test_mode = true;
    
    public $result_url;
    
    public $success_url;
    
    public $failure_url;
    
    public $request_method;
    
    public function getUrlForPayment($order_id, $amount, $description, $currency='RUR', $language='ru')
    {
        $result = $this->getParams($order_id, $amount, $description, $currency, $language);

        return self::PAYMENT_URL . "?" . $result;
    }
    
    public function checkPayment($oOrder)
    {
        $params = array(
            'pg_order_id' => isset($_REQUEST['pg_order_id']) ? $_REQUEST['pg_order_id'] : "",
            'pg_payment_id' => isset($_REQUEST['pg_payment_id']) ? $_REQUEST['pg_payment_id'] : "",
            'pg_amount' => isset($_REQUEST['pg_amount']) ? $_REQUEST['pg_amount'] : "",
            'pg_currency' => isset($_REQUEST['pg_currency']) ? $_REQUEST['pg_currency'] : "",
            'pg_net_amount' => isset($_REQUEST['pg_net_amount']) ? $_REQUEST['pg_net_amount'] : "",
            'pg_ps_amount' => isset($_REQUEST['pg_ps_amount']) ? $_REQUEST['pg_ps_amount'] : "",
            'pg_ps_full_amount' => isset($_REQUEST['pg_ps_full_amount']) ? $_REQUEST['pg_ps_full_amount'] : "",
            'pg_ps_currency' => isset($_REQUEST['pg_ps_currency']) ? $_REQUEST['pg_ps_currency'] : "",
            'pg_payment_system' => isset($_REQUEST['pg_payment_system']) ? $_REQUEST['pg_payment_system'] : "",
            'pg_description' => isset($_REQUEST['pg_description']) ? $_REQUEST['pg_description'] : "",
            'pg_result' => isset($_REQUEST['pg_result']) ? $_REQUEST['pg_result'] : "",
            'pg_payment_date' => isset($_REQUEST['pg_payment_date']) ? $_REQUEST['pg_payment_date'] : "",
            'pg_can_reject' => isset($_REQUEST['pg_can_reject']) ? $_REQUEST['pg_can_reject'] : "",
            'pg_user_phone' => isset($_REQUEST['pg_user_phone']) ? $_REQUEST['pg_user_phone'] : "",
            'pg_salt' => isset($_REQUEST['pg_salt']) ? $_REQUEST['pg_salt'] : "",
            'pg_sig' => isset($_REQUEST['pg_sig']) ? $_REQUEST['pg_sig'] : ""
        );

        // payment with bank card
        if (isset($_REQUEST['pg_card_brand'])) {
            $params += [
                'pg_card_brand' => isset($_REQUEST['pg_card_brand']) ? $_REQUEST['pg_card_brand'] : "",
                'pg_card_pan' => isset($_REQUEST['pg_card_pan']) ? $_REQUEST['pg_card_pan'] : "",
                'pg_card_hash' => isset($_REQUEST['pg_card_hash']) ? $_REQUEST['pg_card_hash'] : "",
                'pg_captured' => isset($_REQUEST['pg_captured']) ? $_REQUEST['pg_captured'] : ""
            ];
        }
        
        if (isset($_REQUEST['pg_description']))
        {
            $params['pg_description'] = $_REQUEST['pg_description'];
        }
        
        $script = 'result';
        $params_check = $params;
        unset($params_check['pg_sig']);
        ksort($params_check);
        $sig_result = implode(";", $params_check);
        
        $sig_check = md5($script . ";" . $sig_result . ";" . $this->secret_key);
        
        if ($sig_check != $params['pg_sig']) { echo "bad sign\n"; exit(); }
        
        if ($oOrder && $oOrder->id && $params['pg_result'] == 1) {
        	$params['pg_ps_amount'] = round($params['pg_ps_amount'], 2);
            
            if ($params['pg_ps_amount'] >= $oOrder->amount) 
            {

                $salt = $this->getSalt();
                $hash = $this->getSig(array('pg_status' => 'ok', 'pg_salt' => $salt), $script);

                header("Content-Type: text/xml");
                echo '<?xml version="1.0" encoding="utf-8"?>
                <response>
                <pg_salt>'.$salt.'</pg_salt>
                <pg_status>ok</pg_status>
                <pg_sig>'.$hash.'</pg_sig>
                </response>';
                Yii::app()->end();
 
                return true;
            }
        }
        
        return false;
    }
    
    protected function getParams($order_id, $amount, $description, $currency='RUR', $language='ru')
    {

        $result = array(
            'pg_merchant_id' => $this->merchant_id, // Идентификатор продавца в Platron
            'pg_order_id' => $order_id,             // Идентификатор платежа в системе продавца. Рекомендуется поддерживать уникальность этого поля.
            'pg_amount' => $amount,                 // Сумма платежа в валюте pg_currency
            'pg_currency' => $currency,                 // Валюта, в которой указана сумма. RUR, USD, EUR.
            'pg_description' => $description,
            'pg_user_ip' => $_SERVER['REMOTE_ADDR'],
            'pg_language' => $language,
            'pg_testing_mode' => intval($this->test_mode),
            'pg_salt' => $this->getSalt() // Случайная строка
        );
        
        if ($this->site_url)
        {
            $result['pg_site_url'] = $this->site_url;
        }
        
        if ($this->result_url)
        {
            $result['pg_result_url'] = $this->result_url;
        }
        
        if ($this->success_url)
        {
            $result['pg_success_url'] = $this->success_url;
        }
        
        if ($this->failure_url)
        {
            $result['pg_failure_url'] = $this->failure_url;
        }
        
        if ($this->request_method)
        {
            $result['pg_request_method'] = $this->request_method;
        }
        
        $sig = $this->getSig($result);
        
        $result['pg_sig'] = $sig;
        
        $res = "";
        
        foreach ($result as $k => $val)
        {
            $res .= "&" . $k . "=" . $val;
        }
        
        return substr($res, 1);
    }
    
    protected function getSalt($length = 6) 
    {
        $validCharacters = "abcdefghijklmnopqrstuxyvwzABCDEFGHIJKLMNOPQRSTUXYVWZ+-*#&@!?";
        $validCharNumber = strlen($validCharacters);

        $result = "";

        for ($i = 0; $i < $length; $i++) 
        {
            $index = mt_rand(0, $validCharNumber - 1);
            $result .= $validCharacters[$index];
        }

        return $result;

    }
    
    protected function getSig($aParams, $script = false)
    {
        if (!$script)
            $script = substr(self::PAYMENT_URL, strrpos(self::PAYMENT_URL, "/") + 1);
        
        ksort($aParams);
        $result = implode(";", $aParams);
        
        return md5($script . ";" . $result . ";" . $this->secret_key);
    }
}