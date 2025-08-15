<?php

namespace Greendot\EshopBundle\Service;

use RuntimeException;
use Greendot\EshopBundle\Event\PasswordResetRequestedEvent;
use Greendot\EshopBundle\Repository\Project\ClientRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;

/**
 * Service for handling password reset functionality.
 */
readonly class PasswordResetService
{
    public function __construct(
        private ClientRepository             $clientRepository,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EventDispatcherInterface     $dispatcher,
    ) {}

    /**
     * Handles a password-reset request for the given e-mail address.
     * @param string $emailFormData
     * @return ResetPasswordToken
     */
    public function requestPasswordReset(string $emailFormData): ResetPasswordToken
    {
        if (!$user = $this->clientRepository->findNonAnonymousByEmail($emailFormData)) {
            throw new RuntimeException('Registrovaný uživatel s tímto e-mailem nebyl nalezen');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
            $this->dispatcher->dispatch(new PasswordResetRequestedEvent($user->getMail(), $resetToken));
        } catch (TooManyPasswordRequestsException $e) {
            $availableAt = $e->getAvailableAt();
            $now = new \DateTimeImmutable('now');
            $remainingSeconds = $availableAt->getTimestamp() - $now->getTimestamp();
            $remainingMinutes = max(1, ceil($remainingSeconds / 60));
            throw new RuntimeException(sprintf('Příliš mnoho pokusů o reset hesla. Zkuste to znovu za %d minut', $remainingMinutes));
        } catch (ResetPasswordExceptionInterface $e) {
            throw new RuntimeException('Nepodařilo se resetovat heslo, zkuste to prosím znovu později');
        }

        return $resetToken;
    }
}