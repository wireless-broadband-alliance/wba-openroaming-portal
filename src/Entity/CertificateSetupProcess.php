<?php

namespace App\Entity;

use App\Enum\CertificateProcessStatus;
use App\Enum\CertificateTestResult;
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
    #[ORM\Column(enumType: CertificateTestResult::class, nullable: true)]
    private ?CertificateTestResult $radsecproxyTestResult = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $freeradiusFormCompletedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $freeradiusConfigAppliedAt = null;
    #[ORM\Column(enumType: CertificateTestResult::class, nullable: true)]
    private ?CertificateTestResult $freeradiusTestResult = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Certificate>
     */
    #[ORM\OneToMany(targetEntity: Certificate::class, mappedBy: 'setupProcess')]
    private Collection $certificates;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $remoteHost = null;

    #[ORM\Column(nullable: true)]
    private ?int $remotePort = null;


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

    public function getRadsecproxyTestResult(): ?CertificateTestResult
    {
        return $this->radsecproxyTestResult;
    }

    public function setRadsecproxyTestResult(?CertificateTestResult $radsecproxyTestResult): static
    {
        $this->radsecproxyTestResult = $radsecproxyTestResult;
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

    public function getFreeradiusTestResult(): ?CertificateTestResult
    {
        return $this->freeradiusTestResult;
    }

    public function setFreeradiusTestResult(?CertificateTestResult $freeradiusTestResult): static
    {
        $this->freeradiusTestResult = $freeradiusTestResult;
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

    public function getRemoteHost(): ?string
    {
        return $this->remoteHost;
    }

    public function setRemoteHost(?string $remoteHost): static
    {
        $this->remoteHost = $remoteHost;

        return $this;
    }

    public function getRemotePort(): ?int
    {
        return $this->remotePort;
    }

    public function setRemotePort(?int $remotePort): static
    {
        $this->remotePort = $remotePort;

        return $this;
    }
}
