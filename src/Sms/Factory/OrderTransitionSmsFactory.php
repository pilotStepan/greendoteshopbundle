<?php

namespace Greendot\EshopBundle\Sms\Factory;

use Exception;
use RuntimeException;
use Psr\Log\LoggerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Dto\SmsMessageDto;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\CurrencyManager;
use Symfony\Contracts\Translation\TranslatorInterface;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[WithMonologChannel('notification.sms')]
readonly class OrderTransitionSmsFactory implements OrderTransitionSmsFactoryInterface
{
    use SmsFactoryTrait;

    public function __construct(
        private TranslatorInterface   $translator,
        private CurrencyManager       $currencyManager,
        private PurchasePriceFactory  $priceFactory,
        private ParameterBagInterface $parameterBag,
        private LoggerInterface       $logger,
    ) {}

    public function create(Purchase $purchase, string $transition): SmsMessageDto
    {
        $phone = $this->processPhone($purchase);
        if (!$phone) {
            $this->logger->error('Invalid or missing phone number', [
                'purchase_id' => $purchase->getId(),
                'transition' => $transition,
            ]);
            throw new RuntimeException('Invalid or missing phone number');
        }

        $state = $purchase->getState();
        $tracking = $purchase->getTransportNumber();
        $amount = null;

        $key = match ($state) {
            'sent'                     => $tracking ? 'sms.order.sent_with_tracking' : 'sms.order.sent',
            'paid', 'ready_for_pickup' => 'sms.order.' . $state,
            default                    => 'sms.order.default',
        };

        if ($state === 'paid') {
            $currency = $this->currencyManager->get();
            $amount = $this->priceFactory
                ->create($purchase, $currency, VatCalculationType::WithVAT)
                ->getPrice(true)
            ;
        }

        $params = array_filter([
            '%id%' => $purchase->getId() ?? '',
            '%tracking%' => $tracking,
            '%amount%' => $amount,
        ], static fn($v) => $v !== null && $v !== '');

        $text = $this->translator->trans($key, $params, 'sms');

        if ($text === '') {
            $this->logger->error('Empty SMS text generated', [
                'purchase_id' => $purchase->getId(),
                'transition' => $transition,
            ]);
            throw new RuntimeException('Empty SMS text generated');
        }

        try {
            $sender = $this->parameterBag->get('project.name');
        } catch (Exception $e) {
            $sender = '';
        }

        return new SmsMessageDto(
            phone: $phone,
            text: $text,
            sender: $sender,
        );
    }
}