<?php

namespace Greendot\EshopBundle\Controller\Simple;

use Greendot\EshopBundle\Service\PasswordResetService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
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

    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    public function apiResetPassword(Request $request, PasswordResetService $passwordResetService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['success' => false, 'message' => 'E-mail je povinný'], 400);
        }

        try {
            $passwordResetService->processSendingPasswordResetEmail($email);
            return $this->json(['success' => true, 'message' => 'E-mail pro reset hesla byl odeslán']);
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}