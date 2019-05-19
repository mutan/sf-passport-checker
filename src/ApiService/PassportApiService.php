<?php

namespace App\ApiService;

use DateTime;
use Exception;
use App\Service\PassportService;
use App\Exception\ServiceMessageException;

class PassportApiService
{
    private $passportService;

    public function __construct(PassportService $passportService)
    {
        $this->passportService = $passportService;
    }

    /**
     * @ApiMethod
     * @return string
     * @throws Exception
     */
    public function version()
    {
        if (!$this->passportService->getVersion()) {
            throw new ServiceMessageException('Empty version');
        }
        $version = new DateTime($this->passportService->getVersion());
        return $version->format('Y-m-d H:i:s');
    }

    /**
     * @ApiMethod
     * @param array $data
     * @return array
     */
    public function check(array $data)
    {
        $result = $this->passportService->check($data);
        return $result;
    }
}
