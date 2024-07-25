<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Api\V1\Controller\ConfigController;
use App\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ApiResource(
    description: "The Setting entity returns configuration options for the application. 
    Each setting consists of a name and an optional value, 
    which can be used to store and return configuration parameters required for  the API.",
    operations: [
        new GetCollection(
            uriTemplate: '/v1/config',
            controller: ConfigController::class,
            shortName: 'Setting',
            description: 'Returns public values from the Setting entity',
            name: 'app_config_settings',
        ),
    ],
)]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * The settings name
     */
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * The settings value
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $value = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }
}
