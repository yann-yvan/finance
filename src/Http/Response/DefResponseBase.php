<?php

namespace NYCorp\Finance\Http\Response;

// Parent chosen once, when the class is first loaded (see LiteApiBridge)
if (LiteApiBridge::enabled()) {
    abstract class DefResponseBase extends \Nycorp\LiteApi\Response\DefResponse
    {
    }
} else {
    abstract class DefResponseBase
    {
    }
}
