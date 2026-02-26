<?php
namespace Greendot\EshopBundle\Entity\Project;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Loggable\Entity\MappedSuperclass\AbstractLogEntry;

/**
 * This class exists only to map the ext_log_entries table to Doctrine.
 */
#[ORM\Entity(repositoryClass: 'Gedmo\Loggable\Entity\Repository\LogEntryRepository')]
#[ORM\Table(name: 'ext_log_entries')]
class LogEntry extends AbstractLogEntry
{
    // You leave this completely empty. 
    // It inherits id, action, logged_at, data, etc., from the vendor class.
}