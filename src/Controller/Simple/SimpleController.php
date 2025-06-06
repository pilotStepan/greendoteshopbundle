<?php

namespace Greendot\EshopBundle\Controller\Simple;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/simple/api', name: 'simple_api_')]
class SimpleController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json(['message' => 'Hello from SimpleController!']);
    }
}