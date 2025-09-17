<?php

namespace Greendot\EshopBundle\Security\User;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class RegisteredUserProvider implements UserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->em->getRepository(Client::class)->findOneBy([
            'mail' => $identifier,
            'isAnonymous' => false,
        ]);

        if (!$user) {
            throw new UserNotFoundException(sprintf('User "%s" not found or is anonymous.', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === Client::class;
    }
}