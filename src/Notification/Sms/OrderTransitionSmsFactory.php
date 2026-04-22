<?php

namespace Greendot\EshopBundle\Notification\Sms;

use Exception;
use RuntimeException;
use Psr\Log\LoggerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Dto\SmsMessageDto;
use Greendot\EshopBundle\Utils\PurchaseHelper;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Symfony\Contracts\Translation\TranslatorInterface;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[WithMonologChannel('notification.sms')]
readonly class OrderTransitionSmsFactory implements OrderTransitionSmsFactoryInterface
{
    public function __construct(
        private TranslatorInterface   $translator,
        private CurrencyManager       $currencyManager,
        private PurchasePriceFactory  $priceFactory,
        private ParameterBagInterface $parameterBag,
        private LoggerInterface       $logger,
    ) {}

    public function create(Purchase $purchase, string $transition): SmsMessageDto
    {
        $phone = PurchaseHelper::processPhone($purchase);
        if (!$phone) {
            $this->logger->error('Invalid or missing phone number', [
                'purchase_id' => $purchase->getId(),
                'transition' => $transition,
            ]);
            throw new RuntimeException('Invalid or missing phone number');
        }

        $tracking = $purchase->getTransportNumber();
        $amount = $this->priceFactory
            ->create(
                $purchase,
                $this->currencyManager->get(),
                VatCalculationType::WithVAT,
            )
            ->getPrice(true)
        ;

        $key = 'sms.order.' . $transition;

        $params = array_filter([
            '%id%' => $purchase->getId() ?? '',
            '%tracking%' => $tracking,
            '%amount%' => $amount,
        ], static fn($v) => $v !== null && $v !== '');

        $text = $this->translator->trans($key, $params, 'sms');
        $transitionTranslationFound = ($text !== '' && $text !== $key);

        if (!$transitionTranslationFound) {
            $key = 'sms.order.default';
            $text = $this->translator->trans($key, $params, 'sms');
        }

        if ($text === '' || $text === $key) {
            $this->logger->error('Empty or missing SMS text generated', [
                'purchase_id' => $purchase->getId(),
                'transition' => $transition,
                'translation_key' => $key,
            ]);
            throw new RuntimeException('Empty or missing SMS text generated');
        }

        if ($transitionTranslationFound && !empty($tracking)) {
            $suffixKey = 'sms.order.' . $transition . '.tracking_suffix';
            $suffix = $this->translator->trans($suffixKey, [], 'sms');
            if ($suffix !== $suffixKey) {
                $text .= ' ' . $suffix . ': ' . $tracking . '.';
            }
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