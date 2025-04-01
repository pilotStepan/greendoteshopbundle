<?php

namespace Greendot\EshopBundle\Entity\Project;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Greendot\EshopBundle\Repository\Project\AddressRepository;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Base abstract class containing common address fields and logic for both client and purchase addresses.
 */
#[ORM\Entity(repositoryClass: AddressRepository::class)]
#[ORM\Table(name: "address")]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap([
    'client' => ClientAddress::class,
    'purchase' => PurchaseAddress::class,
])]
#[ApiResource(
    normalizationContext: ['groups' => ['address:read']],
    denormalizationContext: ['groups' => ['address:write']],
    order: ['id' => 'desc']
)]
//#[ApiFilter(SearchFilter::class, properties: ['purchase' => 'exact'])]
//#[ApiFilter(ClientAddressDateFilter::class)]
abstract class Address
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['client:read', 'address:read', 'purchase:read'])]
    protected ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $street = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $city = null;

    #[ORM\Column(length: 5, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $zip = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $country = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $company = null;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $ic = null;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $dic = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $ship_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $ship_surname = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $ship_company = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $ship_street = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $ship_city = null;

    #[ORM\Column(length: 5, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $ship_zip = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $ship_country = null;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $ship_ic = null;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['client:read', 'client:write', 'address:read', 'address:write', 'purchase:read'])]
    protected ?string $ship_dic = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    #[Groups(['client:read', 'address:read'])]
    protected ?\DateTimeInterface $date_created;

    public function __construct()
    {
        $this->date_created = new \DateTime();
    }

/*    public function createCopy(): static
    {
        $copy = new static();
        foreach (get_object_vars($this) as $name => $value) {
            if ($name === 'id' || $name === 'createdAt') continue;
            $copy->$name = $value;
        }
        return $copy;
    }*/

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

    public function getDateCreated(): ?\DateTimeInterface
    {
        return $this->date_created;
    }

    private function setDateCreated(?\DateTimeInterface $date_created): static
    {
        $this->date_created = $date_created;
        return $this;
    }
}