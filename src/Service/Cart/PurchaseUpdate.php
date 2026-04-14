<?php

namespace Greendot\EshopBundle\Service\Cart;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Dto\PurchaseCheckoutInput;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientAddress;
use Greendot\EshopBundle\Entity\Project\Consent;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseAddress;
use Greendot\EshopBundle\Entity\Project\PurchaseDiscussion;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PurchaseUpdate
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security               $security,
        private LoggerInterface        $logger,
    ) {}

    /**
     * Applies non-null fields from the input DTO to the purchase.
     * Null fields are ignored (left unchanged in DB).
     */
    public function applyFromInput(PurchaseCheckoutInput $input, Purchase $purchase): void
    {
        if ($input->client !== null) {
            $this->applyClient($input->client, $purchase);
        }

        if ($input->address !== null) {
            $this->applyAddress($input->address, $purchase);
        }

        if ($input->consents !== null) {
            $this->applyConsents($input->consents, $purchase);
        }

        if ($input->notes !== null) {
            $this->applyNotes($input->notes, $purchase);
        }
    }

    private function applyClient(array $clientData, Purchase $purchase): void
    {
        $user = $this->security->getUser();

        if ($user instanceof Client) {
            $user
                ->setName($clientData['name'])
                ->setSurname($clientData['surname'])
                ->setPhone($clientData['phone'])
            ;
            $purchase->setClient($user);
            return;
        }

        // For anonymous users: update existing anonymous client or create a new one
        $client = $purchase->getClient();
        if (!$client || !$client->isIsAnonymous()) {
            $client = (new Client())->setIsAnonymous(true);
            $this->em->persist($client);
        }

        $client
            ->setName($clientData['name'])
            ->setSurname($clientData['surname'])
            ->setPhone($clientData['phone'])
            ->setMail($clientData['mail'])
        ;

        if (!$client->getPrimaryAddress() and $purchase->getPurchaseAddress()){
            $address =$purchase->getPurchaseAddress();
        }

        $purchase->setClient($client);
    }

    private function applyAddress(array $addressData, Purchase $purchase): void
    {
        // Replace PurchaseAddress
        $old = $purchase->getPurchaseAddress();
        $purchase->setPurchaseAddress(null);
        if ($old) {
            $this->em->remove($old);
        }

        $address = PurchaseAddress::fromArray($addressData);
        $this->em->persist($address);
        $purchase->setPurchaseAddress($address);

        // Keep logged-in user's ClientAddress in sync
        $user = $this->security->getUser();
        if ($user instanceof Client) {
            $clientAddress = $user->getPrimaryAddress();
            if (!$clientAddress) {
                $clientAddress = ClientAddress::fromArray($addressData);
                $clientAddress->setIsPrimary(true);
                $clientAddress->setClient($user);
                $this->em->persist($clientAddress);
            } else {
                $clientAddress->updateFromArray($addressData);
            }
        }
    }

    private function applyConsents(array $consentIds, Purchase $purchase): void
    {
        foreach ($purchase->getConsents()->toArray() as $consent) {
            $purchase->removeConsent($consent);
        }

        foreach ($consentIds as $id) {
            $consent = $this->em->find(Consent::class, $id);
            if (!$consent) {
                $this->logger->warning('Consent not found', ['consentId' => $id]);
                throw new InvalidArgumentException(sprintf('Souhlas %d nebyl nalezen', $id));
            }
            $purchase->addConsent($consent);
        }
    }

    private function applyNotes(array $notes, Purchase $purchase): void
    {
        foreach ($purchase->getPurchaseDiscussions()->toArray() as $discussion) {
            $purchase->removeDiscussion($discussion);
            $this->em->remove($discussion);
        }

        foreach ($notes as $text) {
            $note = (new PurchaseDiscussion())
                ->setContent($text)
                ->setIsAdmin(false)
            ;
            $this->em->persist($note);
            $purchase->addDiscussion($note);
        }
    }
}