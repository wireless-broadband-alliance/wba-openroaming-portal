<?php

namespace App\DTO;

use App\Entity\DomainBlacklist;
use Symfony\Component\Validator\Constraints as Assert;

class DomainBlacklistDTO
{
    /** @var DomainBlacklistLineDTO[] */
    #[Assert\Valid]
    public array $lines;

    /**
     * @param DomainBlacklist[] $domainBlacklists
     */
    public function __construct(array $domainBlacklists = [])
    {
        foreach ($domainBlacklists as $domainBlacklist) {
            $this->lines[$domainBlacklist->getId()] = new DomainBlacklistLineDTO($domainBlacklist);
        }
    }

    /**
     * @return DomainBlacklist[]
     */
    public function toDomainBlacklist(array $domainBlacklistDB): array
    {
        $blacklist = [];

        foreach ($this->lines as $key => $line) {
            if (is_null($line->id)) {
                $domain = new DomainBlacklist();
            } else {
                $domain = array_find($domainBlacklistDB, fn(DomainBlacklist $domain) => $domain->getId() === $key);
                if (is_null($domain)) {
                    $domain = new DomainBlacklist();
                }
            }

            $domain->setDomain($line->domain);
            $blacklist[] = $domain;
        }

        return $blacklist;
    }
}
