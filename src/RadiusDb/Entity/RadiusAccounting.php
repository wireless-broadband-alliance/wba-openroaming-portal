<?php

namespace App\RadiusDb\Entity;

use App\RadiusDb\Repository\RadiusAccountingRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RadiusAccountingRepository::class)]
#[ORM\Table(name: 'radacct')]
class RadiusAccounting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'radacctid', type: 'bigint')]
    private ?int $radAcctId = null;

    #[ORM\Column(length: 32, nullable: false)]
    private ?string $acctSessionId = null;

    #[ORM\Column(length: 32, nullable: false)]
    private ?string $acctUniqueId = null;

    #[ORM\Column(length: 64, nullable: false)]
    private ?string $username = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $realm = null;

    #[ORM\Column(length: 15, nullable: false)]
    private ?string $nasIpAddress = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $nasPortId = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $nasPortType = null;

    #[ORM\Column(type: 'datetime', name: 'acctstarttime', nullable: true)]
    private ?DateTimeInterface $acctStartTime = null;

    #[ORM\Column(type: 'datetime', name: 'acctupdatetime', nullable: true)]
    private ?DateTimeInterface $acctUpdateTime = null;

    #[ORM\Column(type: 'datetime', name: 'acctstoptime', nullable: true)]
    private ?DateTimeInterface $acctStopTime = null;

    #[ORM\Column(type: 'integer', name: 'acctinterval', nullable: true)]
    private ?int $acctInterval = null;

    #[ORM\Column(type: 'integer', name: 'acctsessiontime', nullable: true)]
    private ?int $acctSessionTime = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $acctAuthentic = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $connectInfo_start = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $connectInfo_stop = null;

    #[ORM\Column(nullable: true)]
    private ?int $acctInputOctets = null;

    #[ORM\Column(nullable: true)]
    private ?int $acctOutputOctets = null;

    #[ORM\Column(length: 50, nullable: false)]
    private ?string $calledStationId = null;

    #[ORM\Column(length: 32, nullable: false)]
    private ?string $acctTerminateCause = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $serviceType = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $framedProtocol = null;

    #[ORM\Column(length: 15, nullable: false)]
    private ?string $framedIpAddress = null;

    #[ORM\Column(length: 45, nullable: false)]
    private ?string $framedIpv6Address = null;

    #[ORM\Column(length: 45, nullable: false)]
    private ?string $framedIpv6Prefix = null;

    #[ORM\Column(length: 44, nullable: false)]
    private ?string $framedInterfaceId = null;

    #[ORM\Column(length: 45, nullable: false)]
    private ?string $delegatedIpv6Prefix = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $class = null;

    public function getRadAcctId(): ?int
    {
        return $this->radAcctId;
    }

    public function setRadAcctId(?int $radAcctId): self
    {
        $this->radAcctId = $radAcctId;

        return $this;
    }

    public function getAcctSessionId(): ?string
    {
        return $this->acctSessionId;
    }

    public function setAcctSessionId(?string $acctSessionId): self
    {
        $this->acctSessionId = $acctSessionId;

        return $this;
    }

    public function getAcctUniqueId(): ?string
    {
        return $this->acctUniqueId;
    }

    public function setAcctUniqueId(?string $acctUniqueId): self
    {
        $this->acctUniqueId = $acctUniqueId;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getRealm(): ?string
    {
        return $this->realm;
    }

    public function setRealm(?string $realm): self
    {
        $this->realm = $realm;

        return $this;
    }

    public function getNasIpAddress(): ?string
    {
        return $this->nasIpAddress;
    }

    public function setNasIpAddress(?string $nasIpAddress): self
    {
        $this->nasIpAddress = $nasIpAddress;

        return $this;
    }

    public function getNasPortId(): ?string
    {
        return $this->nasPortId;
    }

    public function setNasPortId(?string $nasPortId): self
    {
        $this->nasPortId = $nasPortId;

        return $this;
    }

    public function getNasPortType(): ?string
    {
        return $this->nasPortType;
    }

    public function setNasPortType(?string $nasPortType): self
    {
        $this->nasPortType = $nasPortType;

        return $this;
    }

    public function getAcctStartTime(): ?DateTimeInterface
    {
        return $this->acctStartTime;
    }

    public function setAcctStartTime(?DateTimeInterface $acctStartTime): self
    {
        $this->acctStartTime = $acctStartTime;

        return $this;
    }

    public function getAcctUpdateTime(): ?DateTimeInterface
    {
        return $this->acctUpdateTime;
    }

    public function setAcctUpdateTime(?DateTimeInterface $acctUpdateTime): self
    {
        $this->acctUpdateTime = $acctUpdateTime;

        return $this;
    }

    public function getAcctStopTime(): ?DateTimeInterface
    {
        return $this->acctStopTime;
    }

    public function setAcctStopTime(?DateTimeInterface $acctStopTime): self
    {
        $this->acctStopTime = $acctStopTime;

        return $this;
    }

    public function getAcctInterval(): ?int
    {
        return $this->acctInterval;
    }

    public function setAcctInterval(?int $acctInterval): self
    {
        $this->acctInterval = $acctInterval;

        return $this;
    }

    public function getAcctSessionTime(): ?int
    {
        return $this->acctSessionTime;
    }

    public function setAcctSessionTime(?int $acctSessionTime): self
    {
        $this->acctSessionTime = $acctSessionTime;

        return $this;
    }

    public function getAcctAuthentic(): ?string
    {
        return $this->acctAuthentic;
    }

    public function setAcctAuthentic(?string $acctAuthentic): self
    {
        $this->acctAuthentic = $acctAuthentic;

        return $this;
    }

    public function getConnectInfoStart(): ?string
    {
        return $this->connectInfoStart;
    }

    public function setConnectInfoStart(?string $connectInfoStart): self
    {
        $this->connectInfoStart = $connectInfoStart;

        return $this;
    }

    public function getConnectInfoStop(): ?string
    {
        return $this->connectInfoStop;
    }

    public function setConnectInfoStop(?string $connectInfoStop): self
    {
        $this->connectInfoStop = $connectInfoStop;

        return $this;
    }

    public function getAcctInputOctets(): ?int
    {
        return $this->acctInputOctets;
    }

    public function setAcctInputOctets(?int $acctInputOctets): self
    {
        $this->acctInputOctets = $acctInputOctets;

        return $this;
    }

    public function getAcctOutputOctets(): ?int
    {
        return $this->acctOutputOctets;
    }

    public function setAcctOutputOctets(?int $acctOutputOctets): self
    {
        $this->acctOutputOctets = $acctOutputOctets;

        return $this;
    }

    public function getCalledStationId(): ?string
    {
        return $this->calledStationId;
    }

    public function setCalledStationId(?string $calledStationId): self
    {
        $this->calledStationId = $calledStationId;

        return $this;
    }

    public function getAcctTerminateCause(): ?string
    {
        return $this->acctTerminateCause;
    }

    public function setAcctTerminateCause(?string $acctTerminateCause): self
    {
        $this->acctTerminateCause = $acctTerminateCause;

        return $this;
    }

    public function getServiceType(): ?string
    {
        return $this->serviceType;
    }

    public function setServiceType(?string $serviceType): self
    {
        $this->serviceType = $serviceType;

        return $this;
    }

    public function getFramedProtocol(): ?string
    {
        return $this->framedProtocol;
    }

    public function setFramedProtocol(?string $framedProtocol): self
    {
        $this->framedProtocol = $framedProtocol;

        return $this;
    }

    public function getFramedIpAddress(): ?string
    {
        return $this->framedIpAddress;
    }

    public function setFramedIpAddress(?string $framedIpAddress): self
    {
        $this->framedIpAddress = $framedIpAddress;

        return $this;
    }

    public function getFramedIpv6Address(): ?string
    {
        return $this->framedIpv6Address;
    }

    public function setFramedIpv6Address(?string $framedIpv6Address): self
    {
        $this->framedIpv6Address = $framedIpv6Address;

        return $this;
    }

    public function getFramedIpv6Prefix(): ?string
    {
        return $this->framedIpv6Prefix;
    }

    public function setFramedIpv6Prefix(?string $framedIpv6Prefix): self
    {
        $this->framedIpv6Prefix = $framedIpv6Prefix;

        return $this;
    }

    public function getFramedInterfaceId(): ?string
    {
        return $this->framedInterfaceId;
    }

    public function setFramedInterfaceId(?string $framedInterfaceId): self
    {
        $this->framedInterfaceId = $framedInterfaceId;

        return $this;
    }

    public function getDelegatedIpv6Prefix(): ?string
    {
        return $this->delegatedIpv6Prefix;
    }

    public function setDelegatedIpv6Prefix(?string $delegatedIpv6Prefix): self
    {
        $this->delegatedIpv6Prefix = $delegatedIpv6Prefix;

        return $this;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(?string $class): self
    {
        $this->class = $class;

        return $this;
    }
}
