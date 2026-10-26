<?php


namespace NYCorp\Finance\Http\Controllers;


use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use NYCorp\Finance\Exceptions\FinanceException;
use NYCorp\Finance\Http\Response\FinanceResponder;
use NYCorp\Finance\Http\Response\ResponseCode;

/**
 * Minimal record creation used by the library (formerly lite-api's CoreController).
 */
abstract class Controller
{
    use FinanceResponder;

    public function __construct()
    {
    }

    abstract public function getModel(): Model;

    abstract public function addRule(): array;

    /**
     * Validate then record the model; the response body is the fresh model.
     */
    public function save(array $data): JsonResponse
    {
        $validator = Validator::make($data, $this->addRule());
        if ($validator->fails()) {
            return self::financeResponse(ResponseCode::REQUEST_VALIDATION_ERROR, $validator->errors(), $validator->errors()->first());
        }

        try {
            $model = $this->create($data);
            $model->refresh();
            return self::financeResponse(ResponseCode::REQUEST_SUCCESS, $model);
        } catch (FinanceException $exception) {
            Log::debug('Finance add failed gracefully with: ' . $exception->getMessage());
            return $exception->toResponse();
        } catch (Exception $exception) {
            Log::error('Finance add failed with: ' . get_class($exception) . ' ' . $exception->getMessage());
            return self::financeResponse(ResponseCode::REQUEST_EXCEPTION, message: $exception->getMessage());
        }
    }

    /**
     * Record the new model
     */
    public function create(array $data): Model
    {
        return $this->getModel()::firstOrCreate($data);
    }
}
