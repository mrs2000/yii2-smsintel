<?php

namespace mrssoft\smsintel;

/**
 * Отпарвка SMS
 * http://smsintel.ru/
 */
class Transport
{
    /**
     * @var int 1 - использовать HTTPS-адрес, 0 - HTTP
     */
    public $useHttps = 1;

    /**
     * @var string логин для HTTPS-протокола
     */
    public $httpsLogin;

    /**
     * @var string ароль для HTTPS-протокола
     */
    public $httpsPassword;

    /**
     * @var string HTTPS-Адрес, к которому будут обращаться скрипты. Со слэшем на конце.
     */
    public $httpsAddress = 'https://lcab.smsintel.ru/';

    /**
     * @var string HTTP-Адрес, к которому будут обращаться скрипты. Со слэшем на конце.
     */
    public $httpAddress = 'http://lcab.smsintel.ru/';

    /**
     * @var bool разрешить отправку в DEV режиме
     */
    public $enableDev = FALSE;

    public $onlydelivery = 1;

    /**
     * @var int 1 - отправлять через дорогой канал с согласованным именем отправителя. 0 - дешёвый канал.
     */
    public $use_alfasource = 1;

    /**
     * @var string имя отправителя
     */
    public $source;

    /**
     * Проверка баланса
     * @return bool
     */
    public function balance()
    {
        return $this->get($this->request('balance'), 'account');
    }

    /**
     * Список рассылок за определенный период
     * @param string $start
     * @param string $stop
     * @param array $params
     * @return array
     */
    public function reports($start = '0000-00-00', $stop = '0000-00-00', array $params = [])
    {
        if (!isset($params['source'])) {
            $params['source'] = '%';
        }
        if (!isset($params['number'])) {
            $params['number'] = '%';
        }

        $result = $this->request('report', [
            'start'  => $start,
            'stop'   => $stop,
            'source' => $params['source'],
            'number' => $params['number'],
        ]);
        if ($this->get($result, 'code') != 1) {
            $return = ['code' => $this->get($result, 'code'), 'descr' => $this->get($result, 'descr')];
        } else {
            $return = [
                'code'  => $this->get($result, 'code'),
                'descr' => $this->get($result, 'descr'),
            ];
            if (isset($result['sms'])) {
                if (!isset($result['sms'][0])) {
                    $result['sms'] = [$result['sms']];
                }
                $return['sms'] = $result['sms'];
            }
        }

        return $return;
    }

    /**
     * Детализация рассылки
     * @param mixed $smsid
     * @return array
     */
    public function detailReport($smsid)
    {
        $result = $this->request('report', ['smsid' => $smsid]);
        if ($this->get($result, 'code') != 1) {
            $return = ['code' => $this->get($result, 'code'), 'descr' => $this->get($result, 'descr')];
        } else {
            $detail = $result['detail'];
            $return = [
                'code'         => $this->get($result, 'code'),
                'descr'        => $this->get($result, 'descr'),
                'delivered'    => $detail['delivered'],
                'notDelivered' => $detail['notDelivered'],
                'waiting'      => $detail['waiting'],
                'process'      => $detail['process'],
                'enqueued'     => $detail['enqueued'],
                'cancel'       => $detail['cancel'],
                'onModer'      => $detail['onModer'],
            ];
            if (isset($result['sms'])) {
                $return['sms'] = $result['sms'];
            }
        }

        return $return;
    }

    /**
     * Отправка смс
     * @param array|string $params (text => , source =>, datetime => , action =>, onlydelivery =>, smsid =>)
     * @param array|string $phones
     * @return array
     */
    public function send($params, $phones)
    {
        if (!$this->enableDev && YII_ENV == 'dev') {
            return ['code' => 1];
        }

        $someXML = '';
        $phones = (array)$phones;

        if (!is_array($params)) {
            $params = ['text' => $params];
        }

        if (!isset($params['action'])) {
            $params['action'] = 'send';
        }

        if (isset($params['text'])) {
            $params['text'] = htmlspecialchars($params['text']);
        }

        if (!isset($params['onlydelivery'])) {
            $params['onlydelivery'] = $this->onlydelivery;
        }

        if (!isset($params['use_alfasource'])) {
            $params['use_alfasource'] = $this->use_alfasource;
        }

        foreach ($phones as $phone) {
            if (is_array($phone)) {
                if (isset($phone['number'])) {
                    $someXML .= '<to number="'.$phone['number'].'">';
                    if (isset($phone['text'])) {
                        $someXML .= htmlspecialchars($phone['text']);
                    }
                    $someXML .= '</to>';
                }
            } else {
                $someXML .= '<to number="'.$phone.'"></to>';
            }
        }

        $result = $this->request('send', $params, $someXML);

        $code = $this->get($result, 'code');
        if ($code != 1 || $code != 517) {
            $return = [
                'code'  => $code,
                'descr' => $this->get($result, 'descr')
            ];
        } else {
            $return = [
                'code'              => 1,
                'descr'             => $this->get($result, 'descr'),
                'datetime'          => $this->get($result, 'datetime'),
                'action'            => $this->get($result, 'action'),
                'allRecivers'       => $this->get($result, 'allRecivers'),
                'colSendAbonent'    => $this->get($result, 'colSendAbonent'),
                'colNonSendAbonent' => $this->get($result, 'colNonSendAbonent'),
                'priceOfSending'    => $this->get($result, 'priceOfSending'),
                'colsmsOfSending'   => $this->get($result, 'colsmsOfSending'),
                'price'             => $this->get($result, 'price'),
                'smsid'             => $this->get($result, 'smsid'),
            ];
        }

        return $return;
    }

    private function get($responce, $key)
    {
        if (isset($responce[$key])) {
            return $responce[$key];
        }

        return FALSE;
    }

    private function getURL($action)
    {
        if ($this->useHttps == 1) {
            $address = $this->httpsAddress.'API/XML/'.$action.'.php';
        } else {
            $address = $this->httpAddress.'API/XML/'.$action.'.php';
        }
        $address .= '?returnType=json';

        return $address;
    }

    private function request($action, array $params = [], $someXML = '')
    {
        $xml = $this->makeXML($params, $someXML);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getURL($action));
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $result = curl_exec($ch);
        curl_close($ch);

        if (isset($result)) {
            $result = json_decode($result, TRUE);
            if (isset($result['data'])) {
                return $result['data'];
            }

            return [];
        }

        return NULL;
    }

    private function makeXML($params, $someXML = '')
    {
        $xml = '';
        foreach ($params as $key => $value) {
            $xml .= "<$key>$value</$key>";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?><data><login>'
            .htmlspecialchars($this->httpsLogin).'</login><password>'
            .htmlspecialchars($this->httpsPassword).'</password>'
            .$xml.$someXML.'</data>';

        return $xml;
    }
}
