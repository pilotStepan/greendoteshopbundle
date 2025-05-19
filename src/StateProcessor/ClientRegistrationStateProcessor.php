<?php

namespace Greendot\EshopBundle\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;


final readonly class ClientRegistrationStateProcessor implements ProcessorInterface
{

    public function __construct(
        private ProcessorInterface          $processor,
        private UserPasswordHasherInterface $passwordHasher,
        private TokenStorageInterface       $tokenStorage
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Client
    {
        /* @var Client $data */
        if (!$data->getPlainPassword()) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }

        $hashedPassword = $this->passwordHasher->hashPassword(
            $data,
            $data->getPlainPassword()
        );
        $data->setPassword($hashedPassword);
        $data->eraseCredentials();

        $client = $this->processor->process($data, $operation, $uriVariables, $context);

        $this->authenticateClient($client);
        return $client;
    }

    private function authenticateClient(Client $client): void
    {
        $token = new UsernamePasswordToken(
            $client,
            'json_login',
            $client->getRoles()
        );

        $this->tokenStorage->setToken($token);
    }
}
