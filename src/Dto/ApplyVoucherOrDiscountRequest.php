<?php

namespace Greendot\EshopBundle\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ApplyVoucherOrDiscountRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $hash = '',
    ) {}
}