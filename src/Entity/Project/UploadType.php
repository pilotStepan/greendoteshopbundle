<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\UploadTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UploadTypeRepository::class)]
class UploadType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['upload:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['upload:read'])]
    private ?string $name = null;

    /**
     * @var Collection<int, Upload>
     */
    #[ORM\OneToMany(mappedBy: 'uploadType', targetEntity: Upload::class)]
    private Collection $upload;

    public function __construct()
    {
        $this->upload = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Upload>
     */
    public function getUpload(): Collection
    {
        return $this->upload;
    }

    public function addUpload(Upload $upload): static
    {
        if (!$this->upload->contains($upload)) {
            $this->upload->add($upload);
            $upload->setUploadType($this);
        }

        return $this;
    }

    public function removeUpload(Upload $upload): static
    {
        if ($this->upload->removeElement($upload)) {
            // set the owning side to null (unless already changed)
            if ($upload->getUploadType() === $this) {
                $upload->setUploadType(null);
            }
        }

        return $this;
    }


}
