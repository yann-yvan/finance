<?php

namespace NYCorp\Finance\Http\Response;

/**
 * Parsed finance response, built with new DefResponse($jsonResponse).
 *
 * Same methods as nycorp/lite-api's DefResponse (getBody, isSuccess, getMessage, getCode, getCodeKey, isCodeSame,
 * getResponse), and an instance of it when lite-api 1.x is installed (see LiteApiBridge).
 */
class DefResponse extends DefResponseBase
{
}
