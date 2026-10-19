<?php

namespace App\Twig\Components;

use App\Entity\Network;
use App\Entity\User;
use App\Repository\NetworkRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent]
class NetworkSearchForm
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true, url: true)]
    public string $filter = 'all';

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp(writable: true)]
    public int $count = 7;

    #[LiveProp(writable: true)]
    public string $sort = 'createdAt';

    #[LiveProp(writable: true)]
    public string $order = 'desc';

    /** @var Paginator<Network>|null */
    private ?Paginator $cachedNetworks = null;

    private ?int $cachedCounts = null;

    public function __construct(
        private readonly NetworkRepository $networkRepository,
    ) {
    }

    /**
     * @return Paginator<Network>
     */
    #[ExposeInTemplate]
    public function getNetworks(): Paginator
    {
        if (!$this->cachedNetworks instanceof Paginator) {
            $this->cachedNetworks = new Paginator($this->getQueryBuilder(), false);
        }

        return $this->cachedNetworks;
    }

    private function getQueryBuilder(): QueryBuilder
    {
        return $this->networkRepository->searchWithFilter(
            $this->sort,
            $this->order,
            $this->query ?: null,
            $this->page,
            $this->count,
        );
    }

    #[ExposeInTemplate]
    public function getNetworkCounts(): int
    {
        $this->cachedCounts ??= $this->networkRepository->countAll();

        return $this->cachedCounts;
    }

    #[ExposeInTemplate]
    public function getTotalPages(): int
    {
        return (int)ceil(count($this->getNetworks()) / $this->count);
    }

    #[LiveAction]
    public function changeSort(#[LiveArg] string $field): void
    {
        if ($this->sort === $field) {
            $this->order = $this->order === 'desc' ? 'asc' : 'desc';
        } else {
            $this->sort = $field;
            $this->order = 'desc';
        }
    }
}
