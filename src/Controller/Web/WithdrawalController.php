<?php

namespace Greendot\EshopBundle\Controller\Web;

use Psr\Log\LoggerInterface;
use Greendot\EshopBundle\Dto\WithdrawalData;
use Symfony\Component\HttpFoundation\Request;
use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Form\WithdrawalType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Greendot\EshopBundle\Attribute\CustomApiEndpoint;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class WithdrawalController extends AbstractController
{
    #[CustomApiEndpoint]
    #[Route('/api/withdrawal', name: 'form_withdrawal_submit', methods: ['POST'])]
    public function submit(
        Request                                  $request,
        ManageMails                              $manageMails,
        LoggerInterface                          $logger,
        #[Autowire(env: 'COMPANY_EMAIL')] string $shopEmail,
    ): Response
    {
        $form = $this->createForm(WithdrawalType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var WithdrawalData $data */
        $data = $form->getData();

        try {
            $manageMails->sendWithdrawalConfirmation($data, $shopEmail);
        } catch (\Throwable $e) {
            $logger->error('Error sending withdrawal confirmation e-mails', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(['errors' => ['Odstoupení se nepodařilo odeslat. Prosím zkuste to znovu.']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['success' => true]);
    }
}
