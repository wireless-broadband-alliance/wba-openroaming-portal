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

    public function getAcctSessionId(): ?string
    {
        return $this->acctSessionId;
    }

    public function getAcctUniqueId(): ?string
    {
        return $this->acctUniqueId;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }


    public function getRealm(): ?string
    {
        return $this->realm;
    }

    public function getNasIpAddress(): ?string
    {
        return $this->nasIpAddress;
    }


    public function getNasPortId(): ?string
    {
        return $this->nasPortId;
    }

    public function getNasPortType(): ?string
    {
        return $this->nasPortType;
    }

    public function getAcctStartTime(): ?DateTimeInterface
    {
        return $this->acctStartTime;
    }

    public function getAcctUpdateTime(): ?DateTimeInterface
    {
        return $this->acctUpdateTime;
    }


    public function getAcctStopTime(): ?DateTimeInterface
    {
        return $this->acctStopTime;
    }

    public function getAcctInterval(): ?int
    {
        return $this->acctInterval;
    }

    public function getAcctSessionTime(): ?int
    {
        return $this->acctSessionTime;
    }

    public function getAcctAuthentic(): ?string
    {
        return $this->acctAuthentic;
    }

    public function getConnectInfoStart(): ?string
    {
        return $this->connectInfo_start;
    }

    public function getConnectInfoStop(): ?string
    {
        return $this->connectInfo_stop;
    }


    public function getAcctInputOctets(): ?int
    {
        return $this->acctInputOctets;
    }

    public function getAcctOutputOctets(): ?int
    {
        return $this->acctOutputOctets;
    }

    public function getCalledStationId(): ?string
    {
        return $this->calledStationId;
    }

    public function getAcctTerminateCause(): ?string
    {
        return $this->acctTerminateCause;
    }

    public function getServiceType(): ?string
    {
        return $this->serviceType;
    }

    public function getFramedProtocol(): ?string
    {
        return $this->framedProtocol;
    }

    public function getFramedIpAddress(): ?string
    {
        return $this->framedIpAddress;
    }

    public function getFramedIpv6Address(): ?string
    {
        return $this->framedIpv6Address;
    }

    public function getFramedIpv6Prefix(): ?string
    {
        return $this->framedIpv6Prefix;
    }

    public function getFramedInterfaceId(): ?string
    {
        return $this->framedInterfaceId;
    }

    public function getDelegatedIpv6Prefix(): ?string
    {
        return $this->delegatedIpv6Prefix;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }
}
