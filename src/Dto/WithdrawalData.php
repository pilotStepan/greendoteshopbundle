<?php

namespace Greendot\EshopBundle\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class WithdrawalData
{
    #[Assert\NotBlank(message: 'Vyplňte prosím jméno a příjmení.')]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Vyplňte prosím e-mail.')]
    #[Assert\Email(message: 'Zadejte prosím platný e-mail.')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Vyplňte prosím číslo objednávky.')]
    public ?int $orderNumber = null;

    #[Assert\NotBlank(message: 'Vypište prosím zboží, které chcete vrátit.')]
    public ?string $goods = null;

    #[Assert\NotBlank(message: 'Vyplňte prosím bankovní účet.')]
    public ?string $bankAccount = null;
}
