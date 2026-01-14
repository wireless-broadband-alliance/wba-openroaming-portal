<?php

namespace App\Twig\Components;

use App\DTO\SourceBlacklistDTO;
use App\Form\SourceBlacklistType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
class SourceBlacklistForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp(useSerializerForHydration: true)]
    public SourceBlacklistDTO|null $dto = null;

    /**
     * @return FormInterface<SourceBlacklistDTO>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(SourceBlacklistType::class, $this->dto);
    }
}
