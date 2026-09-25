<?php

namespace App\Entity;

use App\Repository\NetworkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NetworkRepository::class)]
#[ORM\Index(name: 'idx_network_geometry', columns: ['geometry'], flags: ['spatial'])]
class Network
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'revisor_geometry', nullable: false)]
    private ?string $geometry = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, AccessPoint> */
    #[ORM\OneToMany(targetEntity: AccessPoint::class, mappedBy: 'network', orphanRemoval: true)]
    private Collection $accessPoints;

    public function __construct()
    {
        $this->accessPoints = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getGeometry(): ?string
    {
        return $this->geometry;
    }

    public function setGeometry(?string $geometry): static
    {
        $this->geometry = $geometry;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /** @return Collection<int, AccessPoint> */
    public function getAccessPoints(): Collection
    {
        return $this->accessPoints;
    }

    public function addAccessPoint(AccessPoint $accessPoint): static
    {
        if (!$this->accessPoints->contains($accessPoint)) {
            $this->accessPoints->add($accessPoint);
            $accessPoint->setNetwork($this);
        }
        return $this;
    }

    public function removeAccessPoint(AccessPoint $accessPoint): static
    {
        if ($this->accessPoints->removeElement($accessPoint) && $accessPoint->getNetwork() === $this) {
            $accessPoint->setNetwork(null);
        }
        return $this;
    }
}
