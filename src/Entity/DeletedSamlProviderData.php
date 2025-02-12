<?php

namespace App\Entity;

use App\Repository\DeletedSamlProviderDataRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeletedSamlProviderDataRepository::class)]
class DeletedSamlProviderData
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $pgpEncryptedJsonFile = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?SamlProvider $samlProvider = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPgpEncryptedJsonFile(): ?string
    {
        return $this->pgpEncryptedJsonFile;
    }

    public function setPgpEncryptedJsonFile(string $pgpEncryptedJsonFile): static
    {
        $this->pgpEncryptedJsonFile = $pgpEncryptedJsonFile;

        return $this;
    }

    public function getSamlProvider(): ?SamlProvider
    {
        return $this->samlProvider;
    }

    public function setSamlProvider(SamlProvider $samlProvider): static
    {
        $this->samlProvider = $samlProvider;

        return $this;
    }
}
