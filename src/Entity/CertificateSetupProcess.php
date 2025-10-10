<?php

namespace App\Entity;

use App\Repository\CertificateSetupProcessRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CertificateSetupProcessRepository::class)]
class CertificateSetupProcess
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $radsecproxyFormCompletedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $radsecproxyConfigAppliedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $radsecproxyOutput = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $freeradiusCompletedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $freeradiusConfigAppliedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $freeradiusOutput = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Certificate>
     */
    #[ORM\OneToMany(targetEntity: Certificate::class, mappedBy: 'setupProcess')]
    private Collection $certificates;

    public function __construct()
    {
        $this->certificates = new ArrayCollection();
    }

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

    public function getRadsecproxyFormCompletedAt(): ?\DateTimeImmutable
    {
        return $this->radsecproxyFormCompletedAt;
    }

    public function setRadsecproxyFormCompletedAt(?\DateTimeImmutable $radsecproxyFormCompletedAt): static
    {
        $this->radsecproxyFormCompletedAt = $radsecproxyFormCompletedAt;

        return $this;
    }

    public function getRadsecproxyConfigAppliedAt(): ?\DateTimeImmutable
    {
        return $this->radsecproxyConfigAppliedAt;
    }

    public function setRadsecproxyConfigAppliedAt(?\DateTimeImmutable $radsecproxyConfigAppliedAt): static
    {
        $this->radsecproxyConfigAppliedAt = $radsecproxyConfigAppliedAt;

        return $this;
    }

    public function getRadsecproxyOutput(): ?string
    {
        return $this->radsecproxyOutput;
    }

    public function setRadsecproxyOutput(?string $radsecproxyOutput): static
    {
        $this->radsecproxyOutput = $radsecproxyOutput;

        return $this;
    }

    public function getFreeradiusCompletedAt(): ?\DateTimeImmutable
    {
        return $this->freeradiusCompletedAt;
    }

    public function setFreeradiusCompletedAt(?\DateTimeImmutable $freeradiusCompletedAt): static
    {
        $this->freeradiusCompletedAt = $freeradiusCompletedAt;

        return $this;
    }

    public function getFreeradiusConfigAppliedAt(): ?\DateTimeImmutable
    {
        return $this->freeradiusConfigAppliedAt;
    }

    public function setFreeradiusConfigAppliedAt(?\DateTimeImmutable $freeradiusConfigAppliedAt): static
    {
        $this->freeradiusConfigAppliedAt = $freeradiusConfigAppliedAt;

        return $this;
    }

    public function getFreeradiusOutput(): ?string
    {
        return $this->freeradiusOutput;
    }

    public function setFreeradiusOutput(?string $freeradiusOutput): static
    {
        $this->freeradiusOutput = $freeradiusOutput;

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

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Certificate>
     */
    public function getCertificates(): Collection
    {
        return $this->certificates;
    }

    public function addCertificate(Certificate $certificate): static
    {
        if (!$this->certificates->contains($certificate)) {
            $this->certificates->add($certificate);
            $certificate->setSetupProcess($this);
        }

        return $this;
    }

    public function removeCertificate(Certificate $certificate): static
    {
        if ($this->certificates->removeElement($certificate)) {
            // set the owning side to null (unless already changed)
            if ($certificate->getSetupProcess() === $this) {
                $certificate->setSetupProcess(null);
            }
        }

        return $this;
    }
}
