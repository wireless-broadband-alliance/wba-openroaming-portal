<?php

namespace App\DTO;

use App\Entity\DomainBlacklist;
use App\Validator\Constraints\DomainNotWhitelisted;
use Symfony\Component\Validator\Constraints as Assert;

class DomainBlacklistLineDTO
{
    #[DomainNotWhitelisted]
    #[Assert\Hostname(message: 'invalidDomain')]
    public ?string $domain = null;

    public ?int $id = null;

    public function __construct(?DomainBlacklist $domainBlacklist = null)
    {
        if (!is_null($domainBlacklist)) {
            $this->domain = $domainBlacklist->getDomain();
            $this->id = $domainBlacklist->getId();
        }
    }
}
