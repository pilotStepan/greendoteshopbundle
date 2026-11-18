<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'comment.extra: free-form JSON, starting with the e-mails sent to the customer about the admin reply';
    }

    public function up(Schema $schema): void
    {
        // the default fills existing rows and covers shops whose bundle version doesn't map the column yet
        $this->addSql("ALTER TABLE `comment` ADD COLUMN IF NOT EXISTS `extra` JSON NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `comment` DROP COLUMN IF EXISTS `extra`');
    }

    public function isTransactional(): bool
    {
        return false; // MariaDB commits DDL implicitly
    }
}
