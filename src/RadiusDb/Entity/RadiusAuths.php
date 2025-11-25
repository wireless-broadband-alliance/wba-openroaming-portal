<?php

namespace App\RadiusDb\Entity;

use App\RadiusDb\Repository\RadiusAuthsRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RadiusAuthsRepository::class)]
#[ORM\Table(name: 'radpostauth')]
class RadiusAuths
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    /** @phpstan-ignore-next-line */
    private ?string $username = null;

    /**
     * @var string The hashed password
     */
    #[ORM\Column(length: 255)]
    /** @phpstan-ignore-next-line */
    private string $pass;

    #[ORM\Column(length: 255)]
    /** @phpstan-ignore-next-line */
    private ?string $reply = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    /** @phpstan-ignore-next-line */
    private ?\DateTimeInterface $authdate = null;

    #[ORM\Column(length: 255)]
    /** @phpstan-ignore-next-line */
    private ?string $class = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getPassword(): ?string
    {
        return $this->pass;
    }

    public function getReply(): ?string
    {
        return $this->reply;
    }

    public function getAuthdate(): ?DateTimeInterface
    {
        return $this->authdate;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }
}
