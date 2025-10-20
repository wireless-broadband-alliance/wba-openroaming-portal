<?php

namespace App\Entity;

use App\Enum\CertificateProcessStatus;
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

    #[ORM\Column(enumType: CertificateProcessStatus::class)]
    private ?CertificateProcessStatus $status = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $radsecproxyFormCompletedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $radsecproxyConfigAppliedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $freeradiusFormCompletedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $freeradiusConfigAppliedAt = null;

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

    public function getStatus(): ?CertificateProcessStatus
    {
        return $this->status;
    }

    public function setStatus(CertificateProcessStatus $status): static
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

    public function getFreeradiusFormCompletedAt(): ?\DateTimeImmutable
    {
        return $this->freeradiusFormCompletedAt;
    }

    public function setFreeradiusFormCompletedAt(?\DateTimeImmutable $freeradiusFormCompletedAt): static
    {
        $this->freeradiusFormCompletedAt = $freeradiusFormCompletedAt;

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
        // set the owning side to null (unless already changed)
        if (
            $this->certificates->removeElement($certificate) &&
            ($this->certificates->removeElement(
                $certificate
            ) && $certificate->getSetupProcess() === $this)
        ) {
            $certificate->setSetupProcess(null);
        }

        return $this;
    }
}
