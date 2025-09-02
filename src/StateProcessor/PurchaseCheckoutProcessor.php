<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\StateProcessor;

use Exception;
use LogicException;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Workflow\Registry;
use ApiPlatform\State\ProcessorInterface;
use Monolog\Attribute\WithMonologChannel;
use Symfony\Bundle\SecurityBundle\Security;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Consent;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Greendot\EshopBundle\Entity\Project\PurchaseAddress;
use Greendot\EshopBundle\Entity\Project\PurchaseDiscussion;
use Greendot\EshopBundle\Service\AffiliateService;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Greendot\EshopBundle\Service\PaymentGateway\PaymentGatewayProvider;

#[WithMonologChannel('purchase.checkout')]
final readonly class PurchaseCheckoutProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Registry               $workflowRegistry,
        private Security               $security,
        private ValidatorInterface     $validator,
        private LoggerInterface        $logger,
        private RequestStack           $requestStack,
        private PaymentGatewayProvider $gatewayProvider,
        private PurchaseUrlGenerator   $urlGenerator,
        private ManagePurchase         $managePurchase,
        private AffiliateService       $affiliateService,
    ) {}

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse|RedirectResponse
    {
        try {
            // 0. Validate input structure
            $violations = $this->validator->validate($data);
            if ($violations->count()) {
                $msg = (string)$violations;
                throw new InvalidArgumentException($msg);
            }

            // 1. Get existing purchase
            $purchase = $this->em->getRepository(Purchase::class)->findOneBySession();
            if (!$purchase) {
                throw new InvalidArgumentException('Košík nenalezen');
            }

            // 2. Validate provided consents exist and store them
            $providedConsents = array_map(
                fn(int $id) => $this->findConsentOrFail($id),
                $data->consents,
            );

            // Wrap in transaction to ensure atomicity, don't wrap logic inside transaction listeners
            $this->em->wrapInTransaction(function () use ($data, $purchase, $providedConsents) {
                // 3. Handle client
                $client = $this->handleClient($data->client);
                $purchase->setClient($client);

                // 4. Address
                $address = $this->createPurchaseAddress($data->address);
                $purchase->setPurchaseAddress($address);

                // 5. Consents
                foreach ($providedConsents as $consent) {
                    $purchase->addConsent($consent);
                }

                // 6. Notes
                foreach ($data->notes as $noteText) {
                    $purchase->addDiscussion(
                        $this->createDiscussion($noteText),
                    );
                }
                
                // 7. affiliate
                $this->affiliateService->setAffiliateToPurchase($purchase);

                // 8. Workflow transitions
                $purchaseWorkflow = $this->workflowRegistry->get($purchase);
                $this->applyTransition($purchaseWorkflow, $purchase, 'create');
                $this->applyTransition($purchaseWorkflow, $purchase, 'receive');
            });

            // 9. Cleanup session
            $this->requestStack->getSession()?->remove('purchase');

            // 10. Build redirect URL
            $redirectUrl = $this->buildRedirectUrl($purchase);
            return new JsonResponse(['redirect' => $redirectUrl], 200);

        } catch (LogicException $e) {
            // Validation errors or other invalid arguments
            return new JsonResponse(['errors' => [$e->getMessage()]], 400);
        } catch (Exception|ORMException $e) {
            // General exceptions or ORM errors
            $this->logger->error('Unexpected exception', ['exception' => $e]);
            return new JsonResponse(['errors' => ['Došlo k neočekávané chybě']], 500);
        }
    }

    private function findConsentOrFail(int $id): Consent
    {
        $consent = $this->em->find(Consent::class, $id);

        if (!$consent) {
            throw new InvalidArgumentException(sprintf('Souhlas %d nebyl nalezen', $id));
        }

        return $consent;
    }

    private function handleClient(array $clientData): Client
    {
        $user = $this->security->getUser();

        // If logged in, update info
        if ($user instanceof Client) {
            return $user
                ->setName($clientData['name'])
                ->setSurname($clientData['surname'])
                ->setPhone($clientData['phone'])
            ;
        }

        // If not, create anonymous client
        $client = (new Client())
            ->setName($clientData['name'])
            ->setSurname($clientData['surname'])
            ->setPhone($clientData['phone'])
            ->setMail($clientData['mail'])
            ->setIsAnonymous(true)
        ;

        $this->em->persist($client);

        return $client;
    }

    private function createPurchaseAddress(array $addressData): PurchaseAddress
    {
        $address = new PurchaseAddress();

        foreach ($addressData as $key => $value) {
            $setter = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($address, $setter)) {
                $address->$setter($value);
            }
        }

        $this->em->persist($address);

        return $address;
    }

    // Save note as PurchaseDiscussion object
    private function createDiscussion(string $text): PurchaseDiscussion
    {
        $note = (new PurchaseDiscussion())
            ->setContent($text)
            ->setIsAdmin(false)
        ;

        $this->em->persist($note);

        return $note;
    }

    private function applyTransition($workflow, Purchase $purchase, string $transition): void
    {
        if (!$workflow->can($purchase, $transition)) {
            $errors = array_map(
                static fn($b) => $b->getMessage(),
                iterator_to_array($workflow->buildTransitionBlockerList($purchase, $transition)),
            );
            throw new LogicException(json_encode($errors));
        }

        // If it should be paid via gateway, apply transition silently (without notifications)
        if ($purchase->getPaymentType()->getPaymentTechnicalAction()) {
            $workflow->apply($purchase, $transition, ['silent' => true]);
        } else {
            $workflow->apply($purchase, $transition);
        }
    }

    private function buildRedirectUrl(Purchase $purchase): string
    {
        // If payment should not be processed by a gateway, early return end screen url
        if (!$purchase->getPaymentType()->getPaymentTechnicalAction()) {
            return $this->urlGenerator->buildOrderEndscreenUrl($purchase);
        }

        try {
            // prepare prices if not set
            if (!$purchase->getTotalPrice()) {
                $this->managePurchase->preparePrices($purchase);
            }
            return $this->gatewayProvider->getByPurchase($purchase)->getPayLink($purchase);
        } catch (Exception $e) {
            $this->workflowRegistry->get($purchase)->apply($purchase, 'payment_issue');
            return $this->urlGenerator->buildOrderEndscreenUrl($purchase);
        }
    }
}
