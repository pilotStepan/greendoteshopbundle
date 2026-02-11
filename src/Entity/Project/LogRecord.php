<?php

namespace Greendot\EshopBundle\Entity\Project;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'log_record',
    indexes: [
        new ORM\Index(name: 'idx_channel_datetime', columns: ['channel', 'datetime']),
        new ORM\Index(name: 'idx_level_datetime', columns: ['level', 'datetime']),
    ]
)]
class LogRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private int $id;

    #[ORM\Column(type: 'datetime_immutable', precision: 6)]
    private \DateTimeImmutable $datetime;

    #[ORM\Column(type: 'string', length: 64)]
    private string $channel;

    #[ORM\Column(type: 'string', length: 16)]
    private string $level;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $context = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $extra = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $formatted = null;
}
