<?php


namespace NYCorp\Finance\Http\Controllers;


use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;
use NYCorp\Finance\FinanceServiceProvider;
use NYCorp\Finance\Http\ResponseParser\Builder;
use NYCorp\Finance\Http\ResponseParser\DefResponse;

class Controller extends BaseController
{
    public function saved(DefResponse $response)
    {

    }

    public function save(array $data)
    {
        $validator = $this->validator($data);
        if ($validator->fails())
            return $this->liteResponse(config(FinanceServiceProvider::FINANCE_CONFIG_NAME.'-code.request.VALIDATION_ERROR'), $validator->errors());
        try {
            $response = new DefResponse($this->liteResponse(config(FinanceServiceProvider::FINANCE_CONFIG_NAME.'-code.request.SUCCESS'), $this->create($data)));
            $this->saved($response);
            return $response->getResponse();
        } catch (\Exception $exception) {
            return $this->liteResponse(config(FinanceServiceProvider::FINANCE_CONFIG_NAME.'-code.request.FAILURE'),env("APP_ENV")=="local"?$exception->getTrace():null, $exception->getMessage());
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

    protected function respondError(Exception $exception){
        return $this->liteResponse(config(FinanceServiceProvider::FINANCE_CONFIG_NAME.'-code.request.FAILURE'),env("APP_ENV")=="local"?$exception->getTrace():null, $exception->getMessage());
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
            $builder = new Builder($code, $message);
            $builder->setData($data);
            $builder->setToken($token);
            return  response()->json($builder->reply(),200, [], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) ;
        } catch (Exception $e) {
            return $this->liteResponse(\config(FinanceServiceProvider::FINANCE_CONFIG_NAME."-code.request.EXCEPTION"), $e->getMessage());
        }
    }
}