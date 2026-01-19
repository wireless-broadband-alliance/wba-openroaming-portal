<?php

namespace App\Entity;

use App\Enum\ProcessStatusType;
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
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\Column(enumType: ProcessStatusType::class)]
    private ?ProcessStatusType $status = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $radsecproxyFormCompletedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $radsecproxyConfigAppliedAt = null;
    #[ORM\Column(nullable: true, enumType: CertificateTestResult::class)]
    private ?CertificateTestResult $radsecproxyTestResult = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $freeradiusFormCompletedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $freeradiusConfigAppliedAt = null;
    #[ORM\Column(nullable: true, enumType: CertificateTestResult::class)]
    private ?CertificateTestResult $freeradiusTestResult = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Certificate>
     */
    #[ORM\OneToMany(targetEntity: Certificate::class, mappedBy: 'setupProcess', cascade: ['persist', 'remove'])]
    private Collection $certificates;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $remoteHost = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isFreeradiusCertEV = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $freeradiusDomainName = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isFreeradiusCloudflare = false;

    public function isFreeradiusCloudflare(): bool
    {
        return $this->isFreeradiusCloudflare;
    }

    public function setIsFreeradiusCloudflare(bool $isFreeradiusCloudflare): void
    {
        $this->isFreeradiusCloudflare = $isFreeradiusCloudflare;
    }

    public function __construct()
    {
        $this->certificates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?ProcessStatusType
    {
        return $this->status;
    }

    public function setStatus(ProcessStatusType $status): static
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
        if ($this->certificates->removeElement($certificate)) {
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

    public function isFreeradiusCertEV(): bool
    {
        return $this->isFreeradiusCertEV;
    }

    public function setIsFreeradiusCertEV(bool $isFreeradiusCertEV): static
    {
        $this->isFreeradiusCertEV = $isFreeradiusCertEV;

        return $this;
    }

    public function getFreeradiusDomainName(): ?string
    {
        return $this->freeradiusDomainName;
    }

    public function setFreeradiusDomainName(?string $freeradiusDomainName): static
    {
        $this->freeradiusDomainName = $freeradiusDomainName;

        return $this;
    }
}
