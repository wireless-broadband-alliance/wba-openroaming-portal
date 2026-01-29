<?php

namespace App\Twig\Components;

use App\DTO\DomainBlacklistAddDTO;
use App\Form\DomainBlacklistType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class DomainBlacklistForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp(useSerializerForHydration: true)]
    public DomainBlacklistAddDTO|null $dto = null;

    /**
     * @return FormInterface<DomainBlacklistAddDTO>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(DomainBlacklistType::class, $this->dto);
    }
}
