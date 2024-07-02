<?php

namespace App\Entity\Project;

use App\Repository\Project\ClientAddressRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientAddressRepository::class)]
class ClientAddress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $zip = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ic = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $dic = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ship_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ship_surname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ship_company = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ship_street = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ship_city = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $ship_zip = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ship_country = null;

    #[ORM\ManyToOne(inversedBy: 'clientAddresses')]
    private ?Client $Client = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_created = null;

    #[ORM\OneToOne(targetEntity: self::class, cascade: ['persist', 'remove'])]
    private ?self $ClientAddress = null;

    #[ORM\OneToMany(mappedBy: 'clientAddress', targetEntity: Purchase::class)]
    private Collection $Purchase;

    #[ORM\Column(nullable: true)]
    private ?bool $is_primary = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ship_ic = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ship_dic = null;

    public function __construct()
    {
        $this->Purchase = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function setZip(?string $zip): static
    {
        $this->zip = $zip;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getIc(): ?string
    {
        return $this->ic;
    }

    public function setIc(?string $ic): static
    {
        $this->ic = $ic;

        return $this;
    }

    public function getDic(): ?string
    {
        return $this->dic;
    }

    public function setDic(?string $dic): static
    {
        $this->dic = $dic;

        return $this;
    }

    public function getShipName(): ?string
    {
        return $this->ship_name;
    }

    public function setShipName(?string $ship_name): static
    {
        $this->ship_name = $ship_name;

        return $this;
    }

    public function getShipSurname(): ?string
    {
        return $this->ship_surname;
    }

    public function setShipSurname(?string $ship_surname): static
    {
        $this->ship_surname = $ship_surname;

        return $this;
    }

    public function getShipCompany(): ?string
    {
        return $this->ship_company;
    }

    public function setShipCompany(?string $ship_company): static
    {
        $this->ship_company = $ship_company;

        return $this;
    }

    public function getShipStreet(): ?string
    {
        return $this->ship_street;
    }

    public function setShipStreet(?string $ship_street): static
    {
        $this->ship_street = $ship_street;

        return $this;
    }

    public function getShipCity(): ?string
    {
        return $this->ship_city;
    }

    public function setShipCity(?string $ship_city): static
    {
        $this->ship_city = $ship_city;

        return $this;
    }

    public function getShipZip(): ?string
    {
        return $this->ship_zip;
    }

    public function setShipZip(?string $ship_zip): static
    {
        $this->ship_zip = $ship_zip;

        return $this;
    }

    public function getShipCountry(): ?string
    {
        return $this->ship_country;
    }

    public function setShipCountry(?string $ship_country): static
    {
        $this->ship_country = $ship_country;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->Client;
    }

    public function setClient(?Client $Client): static
    {
        $this->Client = $Client;

        return $this;
    }

    public function getDateCreated(): ?\DateTimeInterface
    {
        return $this->date_created;
    }

    public function setDateCreated(?\DateTimeInterface $date_created): static
    {
        $this->date_created = $date_created;

        return $this;
    }

    public function getClientAddress(): ?self
    {
        return $this->ClientAddress;
    }

    public function setClientAddress(?self $ClientAddress): static
    {
        $this->ClientAddress = $ClientAddress;

        return $this;
    }

    /**
     * @return Collection<int, Purchase>
     */
    public function getPurchase(): Collection
    {
        return $this->Purchase;
    }

    public function addPurchase(Purchase $purchase): static
    {
        if (!$this->Purchase->contains($purchase)) {
            $this->Purchase->add($purchase);
            $purchase->setClientAddress($this);
        }

        return $this;
    }

    public function removePurchase(Purchase $purchase): static
    {
        if ($this->Purchase->removeElement($purchase)) {
            // set the owning side to null (unless already changed)
            if ($purchase->getClientAddress() === $this) {
                $purchase->setClientAddress(null);
            }
        }

        return $this;
    }

    public function isIsPrimary(): ?bool
    {
        return $this->is_primary;
    }

    public function setIsPrimary(?bool $is_primary): static
    {
        $this->is_primary = $is_primary;

        return $this;
    }

    public function getShipIc(): ?string
    {
        return $this->ship_ic;
    }

    public function setShipIc(?string $ship_ic): static
    {
        $this->ship_ic = $ship_ic;

        return $this;
    }

    public function getShipDic(): ?string
    {
        return $this->ship_dic;
    }

    public function setShipDic(?string $ship_dic): static
    {
        $this->ship_dic = $ship_dic;

        return $this;
    }
}
