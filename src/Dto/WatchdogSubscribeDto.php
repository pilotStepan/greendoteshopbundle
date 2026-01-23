<?php

namespace Greendot\EshopBundle\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Karser\Recaptcha3Bundle\Validator\Constraints as RecaptchaAssert;

final class WatchdogSubscribeDto
{
    public string $type = 'variant_available';
    public ?int $productVariantId = null;

    public ?string $email = null;
    #[Assert\NotBlank]
    #[RecaptchaAssert\Recaptcha3]
    public string $captcha = '';
}