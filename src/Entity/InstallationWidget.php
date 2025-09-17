<?php

namespace App\Entity;

use App\Repository\InstallationWidgetRepository;
use App\Enum\InstallationWidgetStepsEnum;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InstallationWidgetRepository::class)]
class InstallationWidget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: InstallationWidgetStepsEnum::class)]
    private InstallationWidgetStepsEnum $currentStep = InstallationWidgetStepsEnum::DATABASE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $databaseConfiguredAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $adminConfiguredAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $finishedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCurrentStep(): InstallationWidgetStepsEnum
    {
        return $this->currentStep;
    }

    public function setCurrentStep(InstallationWidgetStepsEnum $step): self
    {
        $this->currentStep = $step;
        return $this;
    }

    public function setStepTimestamp(InstallationWidgetStepsEnum $step): self
    {
        $now = new DateTime();
        match ($step) {
            InstallationWidgetStepsEnum::DATABASE             => $this->databaseConfiguredAt = $now,
            InstallationWidgetStepsEnum::ADMIN_CONFIGURATION  => $this->adminConfiguredAt = $now,
            default => null,
        };
        return $this;
    }
}
