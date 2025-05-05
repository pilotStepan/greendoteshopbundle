<?php

namespace Greendot\EshopBundle\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Consent;
use Greendot\EshopBundle\Entity\Project\Note;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseAddress;
use Greendot\EshopBundle\Service\GPWebpay;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\Registry;
use Psr\Log\LoggerInterface;

final readonly class PurchaseSendProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Registry               $workflowRegistry,
        private Security               $security,
        private ValidatorInterface     $validator,
        private GPWebpay               $gpWebpay,
        private UrlGeneratorInterface  $urlGenerator,
        private LoggerInterface        $logger,
        private RequestStack           $requestStack,
    )
    {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse|RedirectResponse
    {
        try {
            // 0. Validate input structure
            $errors = $this->validator->validate($data);
            if (count($errors) > 0) {
                $this->logger->error('Validation errors', ['errors' => $errors]);
                throw new \InvalidArgumentException($errors);
            }

            // 1. Get existing purchase
            $purchase = $this->em->getRepository(Purchase::class)->findOneBySession();
            if (!$purchase) {
                $this->logger->error('Purchase not found in session');
                throw new \InvalidArgumentException('Košík nenalezen');
            }

            // 2. Validate provided consents exist and store them
            $providedConsents = array_map(function ($consentId) {
                $consent = $this->em->find(Consent::class, $consentId);
                if (!$consent) {
                    $this->logger->error('Consent not found', ['consentId' => $consentId]);
                    throw new \InvalidArgumentException("Souhlas $consentId nebyl nalezen");
                }
                return $consent;
            }, $data->consents);

            // 3. Handle client
            $client = $this->handleClient($data->client);
            $purchase->setClient($client);

            // 4. Create purchase address
            $address = $this->createPurchaseAddress($data->address);
            $purchase->setPurchaseAddress($address);

            // 5. Add consents
            foreach ($providedConsents as $consent) {
                $purchase->addConsent($consent);
            }

            // 6. Add notes
            foreach ($data->notes as $noteText) {
                $note = $this->createNote($noteText);
                $purchase->addNote($note);
            }

            // 7. Validate and apply transition
            $purchaseWorkflow = $this->workflowRegistry->get($purchase);
            $this->applyTransition($purchaseWorkflow, $purchase, 'create');
            $this->applyTransition($purchaseWorkflow, $purchase, 'receive');
            $this->em->flush();

            // 8. Process payment after transition
            $redirectUrl = $this->generateRedirectUrl($purchase);

            // 9. Remove purchase from session
            $this->requestStack->getSession()->remove('purchase');

            return new JsonResponse(['redirect' => $redirectUrl], 200);

        } catch (\RuntimeException $e) {
            // Decode JSON error messages if available. If not, wrap the raw message in an array
            $messages = json_decode($e->getMessage(), true) ?: [$e->getMessage()];
            $this->logger->error('RuntimeException during purchase processing', ['errors' => $messages]);
            return new JsonResponse(['errors' => $messages], 409);

        } catch (\InvalidArgumentException $e) {
            // Validation errors or other invalid arguments
            $this->logger->error('InvalidArgumentException during purchase processing', ['error' => $e->getMessage()]);
            return new JsonResponse(['errors' => [$e->getMessage()]], 400);

        } catch (\Exception|ORMException $e) {
            // General exceptions or ORM errors
            $this->logger->error('Unexpected exception during purchase processing', ['exception' => $e]);
            dd($e); // FIXME: left for debugging purposes
            return new JsonResponse(['errors' => ['Došlo k neočekávané chybě']], 500);
        } finally {
            // Ensure the entity manager is closed in case of an ORM error
            if (isset($this->em)) $this->em->close();
        }
    }

    private function handleClient(array $clientData): Client
    {
        $user = $this->security->getUser();

        if ($user instanceof Client) {
            // Update existing client
            $user
                ->setName($clientData['name'])
                ->setSurname($clientData['surname'])
                ->setPhone($clientData['phone']);
            return $user;
        }

        // Create anonymous client
        $client = new Client();
        $client
            ->setName($clientData['name'])
            ->setSurname($clientData['surname'])
            ->setPhone($clientData['phone'])
            ->setMail($clientData['mail'])
            ->setIsAnonymous(true);

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

    private function createNote(string $noteText): Note
    {
        $note = new Note();
        $note->setText($noteText)->setType('order');

        $this->em->persist($note);
        return $note;
    }

    private function generateRedirectUrl(Purchase $purchase): string
    {
        /** - FIXME
         *  - Add relation between PaymentType and PaymentAction
         *  - Then check if ($purchase->getPaymentType()->getPaymentAction()->getId() === 7);
         */
        if ($purchase->getPaymentType()->getId() === 2) {
            return $this->gpWebpay->getPayLink(
                $purchase,
                $purchase->getTotalPrice()
            );
        }

        return $this->urlGenerator->generate('thank_you', [
            'id' => $purchase->getId(),
            'created' => 'true'
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function applyTransition($workflow, Purchase $purchase, string $transition): void
    {
        if (!$workflow->can($purchase, $transition)) {
            $blockers = $workflow->buildTransitionBlockerList($purchase, $transition);
            $errors = array_map(fn($b) => $b->getMessage(), iterator_to_array($blockers));
            $this->logger->error('Workflow transition blocked', [
                'purchaseId' => $purchase->getId(),
                'transition' => $transition,
                'blockers' => $errors
            ]);
            throw new \RuntimeException(json_encode($errors));
        }

        $workflow->apply($purchase, $transition);
    }
}