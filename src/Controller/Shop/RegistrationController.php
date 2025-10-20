<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientAddress;
use Greendot\EshopBundle\Form\ClientAddressFormType;
use Greendot\EshopBundle\Form\RegistrationFormType;
use Greendot\EshopBundle\Repository\Project\ClientRepository;
use Greendot\EshopBundle\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Attribute\CustomApiEndpoint;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    private EmailVerifier $emailVerifier;

    public function __construct(EmailVerifier $emailVerifier)
    {
        $this->emailVerifier = $emailVerifier;
    }

    #[Route('/registrace', name: 'shop_register', priority: 99)]
    public function register(): Response
    {
        return $this->render('shop/registration/register.html.twig');
    }

    #[Route('/dekujeme-za-registraci', name: 'shop_register_thank_you', priority: 9)]
    public function registerThankYou(): Response
    {
        return $this->render('thank-you-pages/thank-you-registration.html.twig');
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, ClientRepository $clientRepository): Response
    {
        $user = $request->get('id');
        $user = $clientRepository->find($user);
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
            return $this->redirectToRoute('web_homepage');
        }

        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('web_homepage');
    }

    #[CustomApiEndpoint]
    #[Route('/api/register', name: 'shop_register_user')]
    public function registerUser(
        Request                     $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface      $entityManager
    ): JsonResponse
    {
        try {
            $json = $request->getContent();
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json(['error' => 'Invalid JSON'], 400);
            }

            $user    = new Client();
            $address = new ClientAddress();

            $form = $this->createForm(RegistrationFormType::class, $user, ['csrf_protection' => false, 'allow_extra_fields' => true]);
            $form->submit($data);

            if ($form->isSubmitted() && $form->isValid()) {
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $form->get('plainPassword')->getData()
                    )
                );
                $user->setIsAnonymous(0);
                $user->setIsVerified(1);

                $addressForm = $this->createForm(ClientAddressFormType::class, $address, ['csrf_protection' => false, 'allow_extra_fields' => true]);
                $addressForm->submit($data);

                if ($addressForm->isSubmitted() && $addressForm->isValid()) {
                    $address->setClient($user);
                    $user->addClientAddress($address);

                    $entityManager->persist($user);
                    $entityManager->persist($address);
                    $entityManager->flush();

                    return $this->json([
                        'message' => 'Registrace proběhla úspěšně, potvrďte vaši e-mailovou adresu'
                    ], 201);
                } else {
                    $errors = [];
                    foreach ($addressForm->getErrors(true) as $error) {
                        $errors[] = [
                            'field'   => $error->getOrigin()->getName(),
                            'message' => $error->getMessage()
                        ];
                    }
                    return $this->json(['errors' => $errors], 400);
                }
            } else {
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = [
                        'field'   => $error->getOrigin()->getName(),
                        'message' => $error->getMessage()
                    ];
                }
                return $this->json(['errors' => $errors], 400);
            }
        } catch (\Exception $e) {
            return $this->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    private function getAllFormErrors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true, true) as $error) {
            $formName            = $error->getOrigin()->getName();
            $errors[$formName][] = $error->getMessage();
        }
        return $errors;
    }

    #[CustomApiEndpoint]
    #[Route('/api/validate-email-{email}', name: 'shop_validate_email')]
    public function checkEmail($email, ClientRepository $clientRepository): Response
    {
        $clients = $clientRepository->findBy(['mail' => $email]);
        $emails  = count($clients);
        return $this->json(
            $emails,
            headers: ['Content-Type' => 'application/json;charset=UTF-8']
        );
    }
}
