<?php

namespace Greendot\EshopBundle\Logging;

use Monolog\Level;
use Monolog\LogRecord;
use Doctrine\DBAL\Connection;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;

final class DoctrineDbalHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly Connection $conn,
        private readonly string     $table = 'log_record',
        string                      $level = Level::Error->name,
        bool                        $bubble = true,
    )
    {
        parent::__construct($level, $bubble);

        $formatter = new JsonFormatter();
        $formatter->includeStacktraces(true);
        $this->setFormatter($formatter);
    }

    protected function write(LogRecord $record): void
    {
        $this->conn->insert($this->table, [
            'datetime' => $record->datetime->format('Y-m-d H:i:s.u'),
            'channel' => $record->channel,
            'level' => $record->level->name,
            'message' => $record->message,
            'context' => json_encode($record->context, JSON_THROW_ON_ERROR),
            'extra' => json_encode($record->extra, JSON_THROW_ON_ERROR),
            'formatted' => $record->formatted,
        ]);
    }
}