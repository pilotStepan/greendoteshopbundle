<?php

namespace Greendot\EshopBundle\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class PurchaseCheckoutInput
{
    #[Groups(['purchase:checkout'])]
    #[Assert\NotNull]
    #[Assert\Type('array')]
    #[Assert\Collection(
        fields: [
            'name' => new Assert\Required([
                new Assert\NotBlank,
                new Assert\Length(max: 100)
            ]),
            'surname' => new Assert\Required([
                new Assert\NotBlank,
                new Assert\Length(max: 100)
            ]),
            'phone' => new Assert\Required([
                new Assert\NotBlank,
                new Assert\Length(max: 20)
            ]),
            'mail' => new Assert\Email(),
        ]
    )]
    public array $client;

    #[Groups(['purchase:checkout'])]
    #[Assert\NotNull]
    #[Assert\Type('array')]
    #[Assert\Collection(
        fields: [
            'street' => new Assert\Required([new Assert\NotBlank]),
            'city' => new Assert\Required([new Assert\NotBlank]),
            'zip' => new Assert\Required([new Assert\NotBlank]),
            'country' => new Assert\Required([new Assert\NotBlank]),
        ],
        allowExtraFields: true,
    )]
    #[Assert\Callback([self::class, 'validateAddress'])]
    public array $address;

    #[Groups(['purchase:checkout'])]
    #[Assert\NotNull]
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Type('integer'),
    ])]
    public array $consents = [];

    #[Groups(['purchase:checkout'])]
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Type('string'),
        new Assert\Length(max: 1000)
    ])]
    public array $notes = [];


    public static function validateAddress(array $address, ExecutionContextInterface $context): void
    {
        // Company fields validation
        $hasCompanyData = !empty($address['ic']) || !empty($address['dic']) || !empty($address['company']);

        if ($hasCompanyData) {
            foreach (['ic', 'company'] as $field) {
                if (empty($address[$field])) {
                    $context->buildViolation('Všechny firemní údaje jsou povinné při zadání kteréhokoliv z nich')
                        ->atPath("address[{$field}]")
                        ->addViolation();
                }
            }
        }

        // Shipping address validation
        $hasShippingData = !empty($address['ship_street']) ||
            !empty($address['ship_city']) ||
            !empty($address['ship_zip']) ||
            !empty($address['ship_country']);

        if ($hasShippingData) {
            foreach (['ship_street', 'ship_city', 'ship_zip', 'ship_country'] as $field) {
                if (empty($address[$field])) {
                    $context->buildViolation('Všechny položky doručovací adresy jsou povinné při zadání kteréhokoliv z nich')
                        ->atPath("address[{$field}]")
                        ->addViolation();
                }
            }
        }
    }
}
