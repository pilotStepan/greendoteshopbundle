<?php

namespace Greendot\EshopBundle\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class PurchaseFinishInput
{
    #[ApiProperty(identifier: true)]
    #[Groups(['purchase:finish'])]
    #[Assert\NotBlank]
    public string $id;

    #[Groups(['purchase:finish'])]
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
            'mail' => new Assert\Required([
                new Assert\Email(),
            ]),
        ]
    )]
    public array $client;

    #[Groups(['purchase:finish'])]
    #[Assert\NotNull]
    #[Assert\Type('array')]
    #[Assert\Collection(
        fields: [
            'street' => new Assert\Required([new Assert\NotBlank]),
            'city' => new Assert\Required([new Assert\NotBlank]),
            'zip' => new Assert\Required([new Assert\NotBlank]),
            'country' => new Assert\Required([new Assert\NotBlank]),
        ]
    )]
    public array $address;

    #[Groups(['purchase:finish'])]
    #[Assert\NotNull]
    #[Assert\All([
        new Assert\Uuid,
        new Assert\NotNull
    ])]
    public array $consents = [];

    #[Groups(['purchase:finish'])]
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Type('string'),
        new Assert\Length(max: 1000)
    ])]
    public array $notes = [];
}