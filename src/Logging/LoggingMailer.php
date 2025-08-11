<?php

namespace Greendot\EshopBundle\Logging;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mailer\Envelope;
use Monolog\Attribute\WithMonologChannel;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator('mailer.mailer')]
#[WithMonologChannel('notification.email')]
final readonly class LoggingMailer implements MailerInterface
{
    public function __construct(
        #[AutowireDecorated]
        private MailerInterface $inner,
        private LoggerInterface $logger,
    ) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        try {
            $this->inner->send($message, $envelope);
        } catch (TransportExceptionInterface $e) {
            $this->logger->critical('Mailer failure', [
                'recipients' => $envelope?->getRecipients() ?? [],
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}
