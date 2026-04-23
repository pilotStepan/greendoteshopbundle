<?php

namespace Greendot\EshopBundle\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class PurchaseCheckoutInput
{
    #[Groups(['purchase:checkout'])]
    #[Assert\NotNull(groups: ['post'])]
    #[Assert\Type('array')]
    #[Assert\Collection(
        fields: [
            'name' => new Assert\Required(
                constraints: [new Assert\NotBlank, new Assert\Length(max: 100)],
                groups: ['Default', 'patch'],
            ),
            'surname' => new Assert\Required(
                constraints: [new Assert\NotBlank, new Assert\Length(max: 100)],
                groups: ['Default', 'patch'],
            ),
            'phone' => new Assert\Required(
                constraints: [new Assert\NotBlank, new Assert\Length(max: 20)],
                groups: ['Default', 'patch'],
            ),
            'mail' => new Assert\Email(),
        ],
        groups: ['Default', 'patch']
    )]
    public ?array $client = null;

    #[Groups(['purchase:checkout'])]
    #[Assert\NotNull(groups: ['post'])]
    #[Assert\Type('array')]
    #[Assert\Collection(
        fields: [
            'street' => new Assert\Required(
                constraints: [new Assert\NotBlank],
                groups: ['post', 'patch_full'],
            ),
            'city' => new Assert\Required(
                constraints: [new Assert\NotBlank],
                groups: ['post', 'patch_full'],
            ),
            'zip' => new Assert\Required(
                constraints: [new Assert\NotBlank],
                groups: ['post', 'patch_full'],
            ),
            'country' => new Assert\Required(
                constraints: [new Assert\NotBlank],
                groups: ['post', 'patch_full'],
            ),
        ],
        groups: ['post', 'patch_full'],
        allowExtraFields: true
    )]
    #[Assert\Collection(
        fields: [
            'street' => new Assert\Optional(
                constraints: [new Assert\NotBlank],
                groups: ['patch'],
            ),
            'city' => new Assert\Optional(
                constraints: [new Assert\NotBlank],
                groups: ['patch'],
            ),
            'zip' => new Assert\Optional(
                constraints: [new Assert\NotBlank],
                groups: ['patch'],
            ),
            'country' => new Assert\Optional(
                constraints: [new Assert\NotBlank],
                groups: ['patch'],
            ),
        ],
        groups: ['patch'],
        allowExtraFields: true,
        allowMissingFields: true
    )]
    #[Assert\Callback([self::class, 'validateAddress'], groups: ['post', 'patch_full'])]
    public ?array $address = null;

    #[Groups(['purchase:checkout'])]
    #[Assert\Type('bool')]
    public bool $partial = false;

    #[Groups(['purchase:checkout'])]
    #[Assert\NotNull(groups: ['post'])]
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Type('integer'),
    ])]
    public ?array $consents = null;

    #[Groups(['purchase:checkout'])]
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Type('string'),
        new Assert\Length(max: 1000),
    ])]
    public ?array $notes = null;


    public static function validateAddress(?array $address, ExecutionContextInterface $context): void
    {
        if ($address === null) {
            return;
        }

        // Company fields validation
        $hasCompanyData = !empty($address['ic']) || !empty($address['dic']) || !empty($address['company']);

        if ($hasCompanyData) {
            foreach (['ic', 'company'] as $field) {
                if (empty($address[$field])) {
                    $context->buildViolation('Všechny firemní údaje jsou povinné při zadání kteréhokoliv z nich')
                        ->atPath("address[{$field}]")
                        ->addViolation()
                    ;
                }
            }
        }

        // Shipping address validation
        $hasShippingData = !empty($address['ship_street']) ||
            !empty($address['ship_city']) ||
            !empty($address['ship_zip']) ||
            !empty($address['ship_country'])
        ;

        if ($hasShippingData) {
            foreach (['ship_street', 'ship_city', 'ship_zip', 'ship_country'] as $field) {
                if (empty($address[$field])) {
                    $context->buildViolation('Všechny položky doručovací adresy jsou povinné při zadání kteréhokoliv z nich')
                        ->atPath("address[{$field}]")
                        ->addViolation()
                    ;
                }
            }
        }
    }
}
