<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\StateProcessor;

use Exception;
use Throwable;
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
use Symfony\Component\Workflow\WorkflowInterface;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Greendot\EshopBundle\Service\AffiliateService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Greendot\EshopBundle\Entity\Project\ClientAddress;
use Greendot\EshopBundle\Entity\Project\PurchaseAddress;
use Greendot\EshopBundle\Entity\Project\PurchaseDiscussion;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Greendot\EshopBundle\Service\PaymentGateway\PaymentGatewayProvider;
use function count;
use function is_array;

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
        $user = $this->security->getUser();
        $isLoggedIn = $user instanceof Client;

        $this->logger->debug('Checkout started', [
            'operation' => $operation->getName(),
            'isLoggedIn' => $isLoggedIn,
            'data' => $data,
        ]);

        try {
            // 0. Validate input structure
            $violations = $this->validator->validate($data);
            if ($violations->count()) {
                $msg = (string)$violations;

                $this->logger->warning('Checkout validation failed', [
                    'violations' => $msg,
                ]);

                throw new InvalidArgumentException($msg);
            }

            // 1. Get existing purchase
            $purchase = $this->em->getRepository(Purchase::class)->findOneBySession();
            if (!$purchase) {
                $this->logger->warning('Checkout failed: purchase not found for session');

                throw new InvalidArgumentException('Košík nenalezen');
            }

            $this->logger->debug('Checkout purchase loaded', [
                'purchaseId' => $purchase->getId(),
                'currentState' => method_exists($purchase, 'getState') ? $purchase->getState() : null,
            ]);

            // 2. Validate provided consents exist and store them
            $consentIds = is_array($data->consents ?? null) ? $data->consents : [];
            $providedConsents = array_map(
                fn(int $id) => $this->findConsentOrFail($id),
                $consentIds,
            );

            $this->logger->info('Checkout DB transaction begin', [
                'purchaseId' => $purchase->getId(),
            ]);

            $this->em->wrapInTransaction(function () use ($data, $purchase, $providedConsents, $isLoggedIn) {
                // 3. Handle client
                $this->logger->debug('Checkout handling client', [
                    'purchaseId' => $purchase->getId(),
                    'isLoggedIn' => $isLoggedIn,
                ]);

                $client = $this->handleClient($data->client, $data->address);
                $purchase->setClient($client);

                $this->logger->debug('Checkout client assigned', [
                    'purchaseId' => $purchase->getId(),
                    'clientId' => $client->getId(),
                    'is_anonymous' => $client->isIsAnonymous(),
                ]);

                // 4. Address
                $address = $this->createPurchaseAddress($data->address);
                $purchase->setPurchaseAddress($address);

                $this->logger->debug('Checkout purchase address persisted', [
                    'purchaseId' => $purchase->getId(),
                    'purchaseAddressId' => $address->getId(),
                ]);

                // 5. Consents
                foreach ($providedConsents as $consent) {
                    $purchase->addConsent($consent);
                }

                $this->logger->debug('Checkout consents attached to purchase', [
                    'purchaseId' => $purchase->getId(),
                    'consentsCount' => count($providedConsents),
                ]);

                // 6. Notes
                $notes = is_array($data->notes ?? null) ? $data->notes : [];
                foreach ($notes as $noteText) {
                    $purchase->addDiscussion($this->createDiscussion($noteText));
                }

                $this->logger->debug('Checkout notes attached to purchase', [
                    'purchaseId' => $purchase->getId(),
                    'notesCount' => count($notes),
                ]);

                // 7. Affiliate
                $this->affiliateService->setAffiliateToPurchase($purchase);

                $this->logger->debug('Checkout affiliate processed', [
                    'purchaseId' => $purchase->getId(),
                ]);

                // 8. Workflow transitions
                $purchaseWorkflow = $this->workflowRegistry->get($purchase);

                $this->logger->info('Checkout applying workflow transitions', [
                    'purchaseId' => $purchase->getId(),
                    'transitions' => ['create', 'receive'],
                ]);

                $this->logger->debug('Applying transitions... (create, receive)');
                $this->applyTransition($purchaseWorkflow, $purchase, 'create');
                $this->applyTransition($purchaseWorkflow, $purchase, 'receive');
            });

            $this->logger->info('Checkout DB transaction committed', [
                'purchaseId' => $purchase->getId(),
            ]);

            // 9. Cleanup session
            $this->requestStack->getSession()?->remove('purchase');

            $this->logger->debug('Checkout session cleaned', [
                'purchaseId' => $purchase->getId(),
                'removedKey' => 'purchase',
            ]);

            // 10. Build redirect URL
            $this->logger->debug('Checkout building redirect URL', [
                'purchaseId' => $purchase->getId(),
                'paymentTechnicalAction' => $purchase->getPaymentType()?->getPaymentTechnicalAction()?->value ?? null,
            ]);

            $redirectUrl = $this->buildRedirectUrl($purchase);

            $this->logger->info('Checkout completed', [
                'purchaseId' => $purchase->getId(),
                'redirectType' => $purchase->getPaymentType()?->getPaymentTechnicalAction() ? 'gateway' : 'endscreen',
            ]);

            return new JsonResponse(['redirect' => $redirectUrl], 200);

        } catch (LogicException|InvalidArgumentException $e) {
            $this->logger->warning('Checkout rejected', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['errors' => [$e->getMessage()]], 400);
        } catch (Exception|ORMException $e) {
            $this->logger->error('Checkout failed with unexpected exception', [
                'exceptionClass' => $e::class,
                'message' => $e->getMessage(),
                'purchaseId' => isset($purchase) ? $purchase->getId() : null,
            ]);

            return new JsonResponse(['errors' => ['Došlo k neočekávané chybě']], 500);
        }
    }

    private function findConsentOrFail(int $id): Consent
    {
        $consent = $this->em->find(Consent::class, $id);

        if (!$consent) {
            $this->logger->warning('Checkout consent not found', [
                'consentId' => $id,
            ]);

            throw new InvalidArgumentException(sprintf('Souhlas %d nebyl nalezen', $id));
        }

        return $consent;
    }

    private function handleClient(array $clientData, array $addressData): Client
    {
        $user = $this->security->getUser();

        // If logged in, update info and address
        if ($user instanceof Client) {
            $this->logger->info('Checkout updating logged-in client', [
                'clientId' => $user->getId(),
                'hasPrimaryAddress' => (bool)$user->getPrimaryAddress(),
            ]);

            $user
                ->setName($clientData['name'])
                ->setSurname($clientData['surname'])
                ->setPhone($clientData['phone'])
            ;

            $address = $user->getPrimaryAddress();
            if (!$address) {
                $address = ClientAddress::fromArray($addressData);
                $address->setIsPrimary(true);
                $address->setClient($user);
                $this->em->persist($address);

                $this->logger->debug('Checkout created new primary address for client', [
                    'clientId' => $user->getId(),
                ]);
            } else {
                $address->updateFromArray($addressData);

                $this->logger->debug('Checkout updated existing primary address for client', [
                    'clientId' => $user->getId(),
                ]);
            }

            $this->em->flush();

            return $user;
        }

        // If not, create anonymous client, without an address saved to client profile
        $this->logger->info('Checkout creating anonymous client', [
            'hasEmailProvided' => isset($clientData['mail']),
        ]);

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
        $address = PurchaseAddress::fromArray($addressData);
        $this->em->persist($address);
        return $address;
    }

    private function createDiscussion(string $text): PurchaseDiscussion
    {
        $note = (new PurchaseDiscussion())
            ->setContent($text)
            ->setIsAdmin(false)
        ;

        $this->em->persist($note);

        return $note;
    }

    private function applyTransition(WorkflowInterface $workflow, Purchase $purchase, string $transition): void
    {
        if (!$workflow->can($purchase, $transition)) {
            $blockers = iterator_to_array($workflow->buildTransitionBlockerList($purchase, $transition));
            $errors = array_map(static fn($b) => $b->getMessage(), $blockers);

            $this->logger->warning('Checkout workflow transition blocked', [
                'purchaseId' => $purchase->getId(),
                'transition' => $transition,
                'blockers' => $errors,
            ]);

            throw new LogicException(json_encode($errors));
        }

        $workflow->apply($purchase, $transition);
    }

    private function buildRedirectUrl(Purchase $purchase): string
    {
        // If gateway should not process payment, early return end screen url
        if (!$purchase->getPaymentType()->getPaymentTechnicalAction()) {
            $url = $this->urlGenerator->buildOrderEndscreenUrl($purchase);

            $this->logger->debug('Checkout redirect resolved: endscreen (no gateway)', [
                'purchaseId' => $purchase->getId(),
            ]);

            return $url;
        }

        try {
            if (!$purchase->getTotalPrice()) {
                $this->logger->info('Checkout preparing prices before gateway redirect', [
                    'purchaseId' => $purchase->getId(),
                ]);

                $this->managePurchase->preparePrices($purchase);

                $this->logger->info('Checkout prices prepared', [
                    'purchaseId' => $purchase->getId(),
                    'totalPrice' => $purchase->getTotalPrice(),
                ]);
            }

            $gateway = $this->gatewayProvider->getByPurchase($purchase);

            $this->logger->info('Checkout requesting gateway pay link', [
                'purchaseId' => $purchase->getId(),
                'gateway' => $gateway::class,
            ]);

            $url = $gateway->getPayLink($purchase);

            $this->logger->info('Checkout redirect resolved: gateway link created', [
                'purchaseId' => $purchase->getId(),
                'gateway' => $gateway::class,
            ]);

            return $url;
        } catch (Throwable $e) {
            $this->workflowRegistry->get($purchase)->apply($purchase, 'payment_issue');

            $this->logger->error('Checkout failed to get payment gateway link, falling back to endscreen', [
                'purchaseId' => $purchase->getId(),
                'exceptionClass' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->urlGenerator->buildOrderEndscreenUrl($purchase);
        }
    }
}
