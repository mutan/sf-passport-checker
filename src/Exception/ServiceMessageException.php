<?php

namespace App\Exception;

use Mutan\ApiServiceBundle\Exception\JsonRpcExceptionInterface;

class ServiceMessageException extends \Exception implements JsonRpcExceptionInterface
{

}
