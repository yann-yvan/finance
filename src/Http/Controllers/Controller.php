<?php


namespace NYCorp\Finance\Http\Controllers;


use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;
use NYCorp\Finance\Http\Core\Finance;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Http\ResponseParser\Builder;
use NYCorp\Finance\Http\ResponseParser\DefResponse;

class Controller extends BaseController
{
    public function save(array $data)
    {
        $validator = $this->validator($data);
        if ($validator->fails())
            return $this->liteResponse(config(Finance::FINANCE_CONFIG_NAME . '-code.request.VALIDATION_ERROR'), $validator->errors());
        try {
            $response = new DefResponse($this->liteResponse(config(Finance::FINANCE_CONFIG_NAME . '-code.request.SUCCESS'), $this->create($data)));
            $this->saved($response);
            return $response->getResponse();
        } catch (\Exception $exception) {
            return $this->liteResponse(config(Finance::FINANCE_CONFIG_NAME . '-code.request.FAILURE'), env("APP_ENV") == "local" ? $exception->getTrace() : null, $exception->getMessage());
        }
    }

    /**
     * Default validator in case of non specification
     *
     * @param $data
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(&$data)
    {
        return Validator::make($data, ['*' => 'required']);
    }

    /**
     * parsing api response according the specification
     *
     * @param      $code
     * @param null $data
     * @param null $message
     * @param null $token
     *
     * @return array|JsonResponse
     */
    public function liteResponse($code, $data = null, $message = null, $token = null)
    {

        try {
            $builder = new Builder($code, mb_convert_encoding($message, 'UTF-8', 'UTF-8'));
            $builder->setData($data);
            $builder->setToken($token);
            return response()->json($builder->reply(), 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            return $this->liteResponse(\config(Finance::FINANCE_CONFIG_NAME . "-code.request.EXCEPTION"),  $e->getMessage());
        }
    }

    public function saved(DefResponse $response)
    {

    }

    protected function reply(PaymentProviderGateway $gateway)
    {
        return $this->liteResponse($gateway->successful() ? config(Finance::FINANCE_CONFIG_NAME . '-code.request.SUCCESS') : config(Finance::FINANCE_CONFIG_NAME . '-code.request.FAILURE'), $gateway->getResponse(), $gateway->getMessage());
    }

    protected function respondError($exception)
    {
        return $this->liteResponse(config(Finance::FINANCE_CONFIG_NAME . '-code.request.FAILURE'), env("APP_ENV") == "local" ? $exception->getTrace() : null, $exception->getMessage());
    }
}