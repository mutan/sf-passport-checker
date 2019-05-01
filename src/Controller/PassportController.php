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
     * get [ ["1234", "123456"] ];
     * return [ ["1234","123456"] ];
     * @Route("/check", name="passport_check", methods={"POST"})
     * @param Request $request
     * @param PassportService $passportService
     * @return JsonResponse
     * @throws Exception
     */
    public function checkAction(Request $request, PassportService $passportService)
    {
        $data = $request->request->get("data");

        if (!is_scalar($data)) {
            throw new Exception('Wrong request format.');
        }

        $data = json_decode($data, true);
        if (!$data) {
            throw new Exception('Not valid json.');
        }

        if (!is_array($data)) {
            throw new Exception('Data must be an array.');
        }

        /*if (!is_string($series) ||
            !is_string($number) ||
            !preg_match('/\d{4}/', $series) ||
            !preg_match('/\d{6}/', $number)
        ) {
            throw new Exception('Wrong format: series must be 4-digit string, and number must be 6-digit string');
        }*/

        $result = $passportService->check($data);
        return $this->json($result);
    }
}
