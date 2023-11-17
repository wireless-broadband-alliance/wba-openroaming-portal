<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $event_datetime = null;

    #[ORM\Column(length: 255)]
    private ?string $event_name = null;

    #[ORM\Column(type: Types::JSON)]
    private ?array $event_metadata = null;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventDatetime(): ?\DateTimeInterface
    {
        return $this->event_datetime;
    }

    public function setEventDatetime(\DateTimeInterface $event_datetime): static
    {
        $this->event_datetime = $event_datetime;

        return $this;
    }

    public function getEventName(): ?string
    {
        return $this->event_name;
    }

    public function setEventName(string $event_name): static
    {
        $this->event_name = $event_name;

        return $this;
    }

    public function getEventMetadata(): ?array
    {
        return $this->event_metadata;
    }

    public function setEventMetadata(array $event_metadata): static
    {
        $this->event_metadata = $event_metadata;

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
}
