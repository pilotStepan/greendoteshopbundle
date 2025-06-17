<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Repository\Project\ClientRepository;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Service for handling password reset functionality.
 */
readonly class PasswordResetService
{
    public function __construct(
        private ClientRepository             $clientRepository,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private ManageMails                  $manageMails,
    )
    {
    }

    /**
     * Processes sending a password reset email to the user.
     *
     * @param string $emailFormData The email address provided by the user.
     *
     * @return ResetPasswordToken The generated reset password token.
     *
     * @throws RuntimeException If the user is not found or if the reset process fails.
     */
    public function processSendingPasswordResetEmail(string $emailFormData): ResetPasswordToken
    {
        if (!$user = $this->clientRepository->findNonAnonymousByEmail($emailFormData)) {
            throw new RuntimeException('Registrovaný uživatel s tímto e-mailem nebyl nalezen');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
            $this->manageMails->sendPasswordResetEmail($user->getMail(), $resetToken);
        } catch (TooManyPasswordRequestsException $e) {
            $availableAt = $e->getAvailableAt();
            $now = new \DateTimeImmutable('now');
            $remainingSeconds = $availableAt->getTimestamp() - $now->getTimestamp();
            $remainingMinutes = max(1, ceil($remainingSeconds / 60));
            throw new RuntimeException(sprintf('Příliš mnoho pokusů o reset hesla. Zkuste to znovu za %d minut', $remainingMinutes));
        } catch (ResetPasswordExceptionInterface|TransportExceptionInterface $e) {
            throw new RuntimeException('Nepodařilo se resetovat heslo, zkuste to prosím znovu později');
        }

        return $resetToken;
    }
}