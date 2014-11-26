<?php
namespace mrssoft\smsintel;

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
     * @var string метод, которым отправляется запрос (curl или file_get_contents)
     */
    public $httpsMethod = 'curl';

    /**
     * @var bool разрешить отправку в DEV режиме
     */
    public $enableDev = false;

    /**
     * Проверка баланса
     * @return bool
     */
    public function balance()
    {
        return $this->get($this->request("balance"), "account");
    }

    public function reports($start = "0000-00-00", $stop = "0000-00-00", $dop = [])
    {
        if (!isset($dop["source"]))
        {
            $dop["source"] = "%";
        }
        if (!isset($dop["number"]))
        {
            $dop["number"] = "%";
        }

        $result = $this->request("report", [
            "start" => $start,
            "stop" => $stop,
            "source" => $dop["source"],
            "number" => $dop["number"],
        ]);
        if ($this->get($result, "code") != 1)
        {
            $return = ["code" => $this->get($result, "code"), "descr" => $this->get($result, "descr")];
        }
        else
        {
            $return = [
                "code" => $this->get($result, "code"),
                "descr" => $this->get($result, "descr"),
            ];
            if (isset($result['sms']))
            {
                if (!isset($result['sms'][0]))
                {
                    $result['sms'] = [$result['sms']];
                }
                $return["sms"] = $result['sms'];
            }
        }

        return $return;
    }

    public function detailReport($smsid)
    {
        $result = $this->request("report", ["smsid" => $smsid]);
        if ($this->get($result, "code") != 1)
        {
            $return = ["code" => $this->get($result, "code"), "descr" => $this->get($result, "descr")];
        }
        else
        {
            $detail = $result["detail"];
            $return = [
                "code" => $this->get($result, "code"),
                "descr" => $this->get($result, "descr"),
                "delivered" => $detail['delivered'],
                "notDelivered" => $detail['notDelivered'],
                "waiting" => $detail['waiting'],
                "process" => $detail['process'],
                "enqueued" => $detail['enqueued'],
                "cancel" => $detail['cancel'],
                "onModer" => $detail['onModer'],
            ];
            if (isset($result['sms']))
            {
                $return["sms"] = $result['sms'];
            }
        }

        return $return;
    }

    /**
     * отправка смс
     * @param array $params (text => , source =>, datetime => , action =>, onlydelivery =>, smsid =>)
     * @param array $phones
     * @return array
     */
    public function send($params = [], $phones = [])
    {
        if (!$this->enableDev)
        {
            if (defined('YII_ENV') && YII_ENV == 'dev') return ['code' => 1];
        }

        $phones = (array)$phones;
        if (!isset($params["action"]))
        {
            $params["action"] = "send";
        }
        $someXML = "";
        if (isset($params["text"]))
        {
            $params["text"] = htmlspecialchars($params["text"]);
        }
        foreach ($phones as $phone)
        {
            if (is_array($phone))
            {
                if (isset($phone["number"]))
                {
                    $someXML .= "<to number='" . $phone['number'] . "'>";
                    if (isset($phone["text"]))
                    {
                        $someXML .= htmlspecialchars($phone["text"]);
                    }
                    $someXML .= "</to>";
                }
            }
            else
            {
                $someXML .= "<to number='$phone'></to>";
            }
        }
        $result = $this->request("send", $params, $someXML);
        if ($this->get($result, "code") != 1)
        {
            $return = ["code" => $this->get($result, "code"), "descr" => $this->get($result, "descr")];
        }
        else
        {
            $return = [
                "code" => 1,
                "descr" => $this->get($result, "descr"),
                "datetime" => $this->get($result, "datetime"),
                "action" => $this->get($result, "action"),
                "allRecivers" => $this->get($result, "allRecivers"),
                "colSendAbonent" => $this->get($result, "colSendAbonent"),
                "colNonSendAbonent" => $this->get($result, "colNonSendAbonent"),
                "priceOfSending" => $this->get($result, "priceOfSending"),
                "colsmsOfSending" => $this->get($result, "colsmsOfSending"),
                "price" => $this->get($result, "price"),
                "smsid" => $this->get($result, "smsid"),
            ];
        }

        return $return;

    }

    private function get($responce, $key)
    {
        if (isset($responce[$key]))
        {
            return $responce[$key];
        }

        return false;
    }

    private function getURL($action)
    {
        if ($this->useHttps == 1)
        {
            $address = $this->httpsAddress . "API/XML/" . $action . ".php";
        }
        else
        {
            $address = $this->httpAddress . "API/XML/" . $action . ".php";
        }
        $address .= "?returnType=json";

        return $address;
    }

    private function request($action, $params = [], $someXML = "")
    {
        $xml = $this->makeXML($params, $someXML);
        if ($this->httpsMethod == "curl")
        {
            $res = $this->request_curl($action, $xml);
        }
        elseif ($this->httpsMethod == "file_get_contents")
        {
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $xml
                ]
            ];

            $context = stream_context_create($opts);

            $res = file_get_contents($this->getURL($action), false, $context);
        }
        if (isset($res))
        {
            $res = json_decode($res, true);
            if (isset($res["data"]))
            {
                return $res["data"];
            }

            return [];
        }
        $this->error("В настройках указан неизвестный метод запроса - '" . $this->httpsMethod . "'");

        return null;
    }

    private function request_curl($action, $xml)
    {
        $address = $this->getURL($action);
        $ch = curl_init($address);
        curl_setopt($ch, CURLOPT_URL, $address);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    private function makeXML($params, $someXML = "")
    {
        $xml = "<?xml version='1.0' encoding='UTF-8'?>
		<data>
			<login>" . htmlspecialchars($this->httpsLogin) . "</login>
			<password>" . htmlspecialchars($this->httpsPassword) . "</password>
			";
        foreach ($params as $key => $value)
        {
            $xml .= "<$key>$value</$key>";
        }
        $xml .= "$someXML
		</data>";

        return $xml;
    }

    private function error($text)
    {
        die($text);
    }
}
