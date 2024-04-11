<?php

namespace App\RadiusDb\Entity;

use App\RadiusDb\Repository\RadiusAuthsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RadiusAuthsRepository::class)]
#[ORM\Table(name: 'radpostauth')]
class RadiusAuths
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $username = null;

    /**
     * @var string The hashed password
     */
    #[ORM\Column(length: 255)]
    private string $pass;

    #[ORM\Column(length: 255)]
    private ?string $reply = null;

    #[ORM\Column(length: 255)]
    private ?string $authdate = null;

    #[ORM\Column(length: 255)]
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

    public function getAuthdate(): ?string
    {
        return $this->authdate;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

}
