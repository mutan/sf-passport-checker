<?php

namespace App\Controller;

use Exception;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class InfoController extends AbstractController
{
    /**
     * @Route("/", name="index", methods={"GET"})
     * @throws Exception
     */
    public function index()
    {
        return [];
    }

    public static function getSubscribedServices()
    {
        return array_merge(parent::getSubscribedServices(), [

        ]);
    }
}
