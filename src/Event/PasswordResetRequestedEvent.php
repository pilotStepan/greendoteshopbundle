<?php

namespace Greendot\EshopBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

/**
 * Dispatched immediately after the reset-password token is generated.
 * The subscriber defers the real sending.
 */
final class PasswordResetRequestedEvent extends Event
{
    public function __construct(
        public string             $recipient, // e-mail address
        public ResetPasswordToken $token,
    ) {}
}
