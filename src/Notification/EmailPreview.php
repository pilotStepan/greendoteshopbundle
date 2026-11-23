<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification;

final readonly class EmailPreview implements \JsonSerializable
{
    /**
     * @param list<string> $to
     * @param list<string> $cc
     */
    public function __construct(
        public string $subject,
        public array  $to,
        public array  $cc,
        public string $html,
    ) {}

    /**
     * @return array{subject: string, to: list<string>, cc: list<string>, html: string}
     */
    public function jsonSerialize(): array
    {
        return ['subject' => $this->subject, 'to' => $this->to, 'cc' => $this->cc, 'html' => $this->html];
    }
}
