<?php

namespace App\Controller;

use DateTime;
use Exception;
use App\Service\PassportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PassportController extends AbstractController
{
    /**
     * @Route("/version", name="passport_version", methods={"GET"})
     * @param PassportService $passportService
     * @return JsonResponse
     * @throws Exception
     */
    public function versionAction(PassportService $passportService)
    {
        $version = new DateTime($passportService->getVersion());
        return $this->json($version->format('Y-m-d H:i:s'));
    }

    /**
     * @Route("/check", name="passport_check", methods={"POST"})
     * @param Request $request
     * @param PassportService $passportService
     * @return JsonResponse
     * @throws Exception
     */
    public function checkAction(Request $request, PassportService $passportService)
    {
        $data = $request->request->get("data");


        if (is_scalar($data)) {
            $data = @json_decode($data, true);
        }
        $result = [];
        if (is_array($data)) {
            $result = $passportService->check($data);
        }

        /*if (!is_string($series) ||
            !is_string($number) ||
            !preg_match('/\d{4}/', $series) ||
            !preg_match('/\d{6}/', $number)
        ) {
            throw new Exception('Wrong format: series must be 4-digit string, and number must be 6-digit string');
        }*/

        return $this->json($result);
    }
}
