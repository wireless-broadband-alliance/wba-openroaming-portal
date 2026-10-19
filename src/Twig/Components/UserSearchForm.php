<?php

namespace App\Twig\Components;

use App\Entity\User;
use App\Form\RevokeProfilesType;
use App\Repository\UserRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent]
class UserSearchForm
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

    /** @var Paginator<User>|null */
    private ?Paginator $cachedUsers = null;

    /** @var array<string, int>|null */
    private ?array $cachedCounts = null;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly FormFactoryInterface $formFactory,
        private readonly Security $security,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * @return Paginator<User>
     */
    #[ExposeInTemplate]
    public function getUsers(): Paginator
    {
        if (!$this->cachedUsers instanceof Paginator) {
            $this->cachedUsers = new Paginator($this->getQueryBuilder());
        }

        return $this->cachedUsers;
    }

    #[ExposeInTemplate]
    public function getTotalPages(): int
    {
        return (int)ceil(count($this->getUsers()) / $this->count);
    }

    private function getQueryBuilder(): QueryBuilder
    {
        return $this->userRepository->searchWithFilter(
            $this->filter,
            $this->sort,
            $this->order,
            $this->query ?: null,
            $this->page,
            $this->count,
        );
    }

    /**
     * @return array<string, int>
     */
    #[ExposeInTemplate]
    public function getUserCounts(): array
    {
        $this->cachedCounts ??= [
            'all' => $this->userRepository->countUsers(null, 'all'),
            'verified' => $this->userRepository->countVerifiedUsers(),
            'banned' => $this->userRepository->countBannedUsers(),
        ];

        return $this->cachedCounts;
    }

    #[ExposeInTemplate]
    public function getFormRevokeProfiles(): FormView
    {
        return $this->formFactory
            ->create(RevokeProfilesType::class, $this->security->getUser())
            ->createView();
    }

    #[ExposeInTemplate]
    public function getExportUsers(): mixed
    {
        return $this->parameterBag->get('app.export_users');
    }

    #[ExposeInTemplate]
    public function getDeleteUsers(): mixed
    {
        return $this->parameterBag->get('app.pgp_public_key');
    }

    #[LiveAction]
    public function changeFilter(#[LiveArg] string $filter): void
    {
        $this->filter = $filter;
        $this->page = 1;
    }

    #[LiveAction]
    public function prevPage(): void
    {
        $this->page--;
    }

    #[LiveAction]
    public function nextPage(): void
    {
        $this->page++;
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
