<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\EntityLogEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Loggable\Entity\MappedSuperclass\AbstractLogEntry;

/**
 * Log entry class for every #[Gedmo\Loggable] entity in the `project` EM
 * Used in CMS
 */
#[ORM\Entity(repositoryClass: EntityLogEntryRepository::class)]
#[ORM\Table(name: 'ext_log_entries', options: ['row_format' => 'DYNAMIC'])]
#[ORM\Index(name: 'log_class_lookup_idx', columns: ['object_class'])]
#[ORM\Index(name: 'log_date_lookup_idx', columns: ['logged_at'])]
#[ORM\Index(name: 'log_user_lookup_idx', columns: ['username'])]
#[ORM\Index(name: 'log_version_lookup_idx', columns: ['object_id', 'object_class', 'version'])]
#[ORM\Index(name: 'log_batch_lookup_idx', columns: ['batch_id'])]
class EntityLogEntry extends AbstractLogEntry
{
    #[ORM\Column(name: 'locale', length: 8, nullable: true)]
    protected ?string $locale = null;

    #[ORM\Column(name: 'batch_id', length: 36, nullable: true)]
    protected ?string $batchId = null;

    #[ORM\Column(name: 'relations', type: 'json', nullable: true)]
    protected ?array $relations = null;

    #[ORM\Column(name: 'revert_of_version', type: 'integer', nullable: true)]
    protected ?int $revertOfVersion = null;

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getBatchId(): ?string
    {
        return $this->batchId;
    }

    public function setBatchId(?string $batchId): self
    {
        $this->batchId = $batchId;

        return $this;
    }

    /**
     * @return array<string, array{added: array<int|string>, removed: array<int|string>}>|null
     */
    public function getRelations(): ?array
    {
        return $this->relations;
    }

    /**
     * @param array<string, array{added: array<int|string>, removed: array<int|string|array{id: int|string, extra: array<string, mixed>}>}>|null $relations
     */
    public function setRelations(?array $relations): self
    {
        $this->relations = $relations;

        return $this;
    }

    public function getRevertOfVersion(): ?int
    {
        return $this->revertOfVersion;
    }

    public function setRevertOfVersion(?int $revertOfVersion): self
    {
        $this->revertOfVersion = $revertOfVersion;

        return $this;
    }
}
