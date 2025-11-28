<?php

namespace App\Entity;

use App\Enum\ProcessStatusType;
use App\Repository\SystemResetRequestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemResetRequestRepository::class)]
class SystemResetRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: ProcessStatusType::class)]
    private ?ProcessStatusType $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToOne(inversedBy: 'systemResetRequest', cascade: ['persist', 'remove'])]
    private ?InstallationProgress $installationProgress = null;

    #[ORM\OneToOne(inversedBy: 'systemResetRequest', cascade: ['persist', 'remove'])]
    private ?CertificateSetupProcess $certificateSetupProcess = null;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getInstallationProgress(): ?InstallationProgress
    {
        return $this->installationProgress;
    }

    public function setInstallationProgress(?InstallationProgress $installationProgress): static
    {
        $this->installationProgress = $installationProgress;

        return $this;
    }

    public function getCertificateSetupProcess(): ?CertificateSetupProcess
    {
        return $this->certificateSetupProcess;
    }

    public function setCertificateSetupProcess(?CertificateSetupProcess $certificateSetupProcess): static
    {
        $this->certificateSetupProcess = $certificateSetupProcess;

        return $this;
    }
}
