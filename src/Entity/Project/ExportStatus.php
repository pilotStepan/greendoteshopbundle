<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Entity\Project\Export;
use Greendot\EshopBundle\Repository\Project\ExportStatusRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExportStatusRepository::class)]
class ExportStatus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalMessages = null;

    #[ORM\Column]
    private ?int $failedCount = null;

    #[ORM\Column]
    private ?int $successCount = null;

    #[ORM\OneToOne(inversedBy: 'exportStatus', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Export $export = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $remark = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTotalMessages(): ?int
    {
        return $this->totalMessages;
    }

    public function setTotalMessages(?int $totalMessages): static
    {
        $this->totalMessages = $totalMessages;

        return $this;
    }

    public function getFailedCount(): ?int
    {
        return $this->failedCount;
    }

    public function setFailedCount(int $failedCount): static
    {
        $this->failedCount = $failedCount;

        return $this;
    }

    public function getSuccessCount(): ?int
    {
        return $this->successCount;
    }

    public function setSuccessCount(int $successCount): static
    {
        $this->successCount = $successCount;

        return $this;
    }

    public function getExport(): ?Export
    {
        return $this->export;
    }

    public function setExport(Export $export): static
    {
        $this->export = $export;

        return $this;
    }

    public function getRemark(): ?string
    {
        return $this->remark;
    }

    public function setRemark(?string $remark): static
    {
        $this->remark = $remark;

        return $this;
    }
}
