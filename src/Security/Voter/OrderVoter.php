<?php

namespace Greendot\EshopBundle\Security\Voter;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OrderVoter extends Voter
{

    const VIEW = 'view';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW])) {
            return false;
        }

        if (!$subject instanceof Purchase) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $client = $token->getUser();

        if (!$client instanceof Client) {
            return false;
        }


        $purchase = $subject;


        return match($attribute) {
            self::VIEW => $this->canView($purchase, $client),
            default => throw new \LogicException('This code should not be reached!')
        };    
    }


    private function canView(Purchase $purchase, Client $client) : bool
    {
        if ($purchase->getClient() === $client) {
            return true;
        }
        return false;
    }
}