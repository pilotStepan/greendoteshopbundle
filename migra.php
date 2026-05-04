<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421AddPurchaseWorkflowFields extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add marking and workflow_flags to purchase, migrate data from legacy state field';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Add columns as nullable so we can populate before enforcing NOT NULL
        $this->addSql('ALTER TABLE `purchase` ADD `marking` JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE `purchase` ADD `workflow_flags` JSON DEFAULT NULL');

        // Step 2: Map state -> marking
        $stateMarkings = [
            'draft'            => ['cart'                => 1],
            'wishlist'         => ['wishlist'            => 1],
            'new'              => ['cart'                => 1],
            'receive'          => ['log_pending'         => 1, 'pay_pending'          => 1, 'log_track_cancellable' => 1, 'pay_track_cancellable' => 1],
            'paid'             => ['log_pending'         => 1, 'pay_paid'             => 1, 'log_track_cancellable' => 1, 'pay_track_cancellable' => 1],
            'not_paid'         => ['log_pending'         => 1, 'pay_pending'          => 1, 'log_track_cancellable' => 1, 'pay_track_cancellable' => 1],
            'ready_for_pickup' => ['log_ready_for_pickup'=> 1, 'pay_paid'             => 1, 'log_track_cancellable' => 1, 'pay_track_done'        => 1],
            'ready_for_send'   => ['log_ready_to_ship'  => 1, 'pay_paid'             => 1, 'log_track_cancellable' => 1, 'pay_track_done'        => 1],
            'picked_up'        => ['completed'           => 1],
            'cancelled'        => ['cancelled'           => 1],
        ];

        foreach ($stateMarkings as $state => $marking) {
            $json = json_encode($marking);
            $this->addSql("UPDATE `purchase` SET `marking` = '$json' WHERE `state` = '$state'");
        }

        // sent: split on whether invoice_number is set (indicates payment was confirmed)
        $this->addSql(
            "UPDATE `purchase` SET `marking` = '{\"log_shipped\":1,\"pay_paid\":1,\"pay_track_done\":1}'"
            . " WHERE `state` = 'sent' AND `invoice_number` IS NOT NULL"
        );
        $this->addSql(
            "UPDATE `purchase` SET `marking` = '{\"log_shipped\":1,\"pay_pending\":1,\"pay_track_cancellable\":1}'"
            . " WHERE `state` = 'sent' AND `invoice_number` IS NULL"
        );

        // Fallback for any unmapped / unknown states
        $this->addSql("UPDATE `purchase` SET `marking` = '{\"draft\":1}' WHERE `marking` IS NULL");

        // Step 3: Initialise workflow_flags to empty object for all rows
        $this->addSql("UPDATE `purchase` SET `workflow_flags` = '{}' WHERE `workflow_flags` IS NULL");

        // not_paid state -> payment_error flag
        $this->addSql(
            "UPDATE `purchase` SET `workflow_flags` = JSON_SET(`workflow_flags`, '$.payment_error', 1)"
            . " WHERE `state` = 'not_paid'"
        );

        // marking contains pay_paid key OR original state was picked_up -> payment_success flag
        $this->addSql(
            "UPDATE `purchase` SET `workflow_flags` = JSON_SET(`workflow_flags`, '$.payment_success', 1)"
            . " WHERE JSON_CONTAINS_PATH(`marking`, 'one', '$.pay_paid') OR `state` = 'picked_up'"
        );

        // Step 4: Enforce NOT NULL now that every row has a value
        $this->addSql('ALTER TABLE `purchase` CHANGE `marking` `marking` JSON NOT NULL');
        $this->addSql('ALTER TABLE `purchase` CHANGE `workflow_flags` `workflow_flags` JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `purchase` DROP COLUMN `marking`');
        $this->addSql('ALTER TABLE `purchase` DROP COLUMN `workflow_flags`');
    }
}
