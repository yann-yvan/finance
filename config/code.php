<?php
/**
 * Created by PhpStorm.
 * User: yann-yvan
 * Date: 08/09/18
 * Time: 13:58
 */
return [

    /*
     * Token message code
     */
    'token' => [
        'TOKEN_EXPIRED' => -1,
        'BLACK_LISTED_TOKEN' => -2,
        'INVALID_TOKEN' => -3,
        'NO_TOKEN' => -4,
        'USER_NOT_FOUND' => -5,
    ],

    /*
    * Common request message code
    */
    'request' => [
        'SUCCESS' => 1000,
        'FAILURE' => -1001,
        'VALIDATION_ERROR' => -1002,
        'EXPIRED' => -1003,
        'TRYING_TO_INSERT_DUPLICATE' => -1004,
        'NOT_AUTHORIZED' => -1005,
        'EXCEPTION' => -1006,
        'NOT_FOUND' => -1007,
        'WRONG_JSON_FORMAT' => -1008
    ],

    /*
     * Authentication message code
     */
    'auth' => [
        'ACCOUNT_NOT_VERIFY' => -1100,
        'WRONG_USERNAME' => -1101,
        'WRONG_PASSWORD' => -1102,
        'WRONG_CREDENTIALS' => -1103,
        'ACCOUNT_VERIFIED' => 1104,
        'NOT_EXISTS' => -1105,
    ],
];
