<?php

namespace App\Entity;

use App\Repository\TwoFactorAuthenticationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TwoFactorAuthenticationRepository::class)]
class TwoFactorAuthentication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'twoFactorAuthentication', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $secret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $codeGeneratedAt = null;

    /**
     * @var Collection<int, OTPcode>
     */
    #[ORM\OneToMany(mappedBy: 'twoFactorAuthentication', targetEntity: OTPcode::class, orphanRemoval: true)]
    private Collection $oTPcodes;

    public function __construct()
    {
        $this->oTPcodes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCodeGeneratedAt(): ?\DateTimeInterface
    {
        return $this->codeGeneratedAt;
    }

    public function setCodeGeneratedAt(?\DateTimeInterface $codeGeneratedAt): static
    {
        $this->codeGeneratedAt = $codeGeneratedAt;

        return $this;
    }

    /**
     * @return Collection<int, OTPcode>
     */
    public function getOTPcodes(): Collection
    {
        return $this->oTPcodes;
    }

    public function addOTPcode(OTPcode $oTPcode): static
    {
        if (!$this->oTPcodes->contains($oTPcode)) {
            $this->oTPcodes->add($oTPcode);
            $oTPcode->setTwoFactorAuthentication($this);
        }

        return $this;
    }

    public function removeOTPcode(OTPcode $oTPcode): static
    {
        // set the owning side to null (unless already changed)
        if (
            $this->oTPcodes->removeElement($oTPcode) &&
            ($this->oTPcodes->removeElement($oTPcode) &&
                ($this->oTPcodes->removeElement($oTPcode) &&
                    ($this->oTPcodes->removeElement($oTPcode) &&
                        $oTPcode->getTwoFactorAuthentication() === $this)))) {
        $oTPcode->setTwoFactorAuthentication(null);
        }
          return $this;
        }
}
