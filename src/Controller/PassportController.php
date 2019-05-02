<?php

namespace App\Controller;

use DateTime;
use Exception;
use InvalidArgumentException;
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
     * // TODO
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
        $result = $request->getContent();
        $jsonResult = json_decode($result, true);
        if (!$jsonResult) {
            throw new InvalidArgumentException('Not valid json.');
        }
        $data = $jsonResult['data'];
        if (!is_array($jsonResult)) {
            throw new InvalidArgumentException('Parameter "data" must be an array.');
        }
        $result = $passportService->check($data);
        return $this->json($result);
    }
}
