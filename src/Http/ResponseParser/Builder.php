<?php


namespace NYCorp\Finance\Http\ResponseParser;


use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use NYCorp\Finance\FinanceServiceProvider;

class Builder
{
    /*
       * Class properties
       */
    private $message = null;
    private $status = false;
    private $code = 0;
    private $data = null;
    private $token = null;

    /**
     * Code constructor.
     * @param      $code
     * @param null $message
     * @throws \Exception
     */
    public function __construct($code, $message = null)
    {
        if ($this->isNotDocCode($code))
            throw new \Exception('Response code not found please refer to documentation');
        $this->status = $code > 0;
        $this->code = abs($code);
        $this->message = $this->defaultMessage($code, $message);
    }

    /**
     * @param null $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @param null $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @return array
     */
    public function reply()
    {
        $data = [
            'status' => $this->status,
            'message' => $this->message,
            'code' => $this->code,
            'data' => $this->data,
        ];
        if ($this->token != null)
            $data['token'] = $this->token;

        return $data;
    }

    /**
     * Check if send code exist in doc code
     * @param  $code
     * @return bool
     */
    private function isNotDocCode($code)
    {
        $codes = array();
        foreach (config(FinanceServiceProvider::FINANCE_CONFIG_NAME.'-code') as $item => $value) {
            $codes = array_merge($codes, array_values($value));
        }
        return !in_array($code, $codes);
    }

    private function defaultMessage($code, $message)
    {
        if (empty($message))
            foreach (config(FinanceServiceProvider::FINANCE_CONFIG_NAME.'-code') as $item => $value) {
                foreach ($value as $key => $val)
                    if ($val == $code)
                        return Str::upper($item) . ' ' . $key;
            }

        return $message;
    }
}
