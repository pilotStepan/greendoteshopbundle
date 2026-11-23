<?php

namespace Greendot\EshopBundle\Tests\Entity;

use Greendot\EshopBundle\Entity\Project\Watchdog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WatchdogEmailValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    #[DataProvider('emailProvider')]
    public function testSubscribeEmailValidation(string $email, bool $valid): void
    {
        $watchdog = (new Watchdog())->setEmail($email);

        $violations = $this->validator->validateProperty($watchdog, 'email', ['watchdog:subscribe']);

        $this->assertSame($valid, count($violations) === 0, (string)$violations);
    }

    public static function emailProvider(): array
    {
        return [
            'plain' => ['user@example.com', true],
            'plus tag' => ['user+tag@example.cz', true],
            // passes html5 mode but Symfony Mailer rejects it when sending
            'consecutive dots' => ['sanat..kumara@aol.com', false],
            'no domain' => ['user@', false],
        ];
    }
}
