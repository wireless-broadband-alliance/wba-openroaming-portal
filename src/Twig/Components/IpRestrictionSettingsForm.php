<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\DTO\IpRestrictionSettingsDTO;
use App\Form\IpRestrictionSettingsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
class IpRestrictionSettingsForm extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public ?IpRestrictionSettingsDTO $ipRestrictionSettingsDTO = null;

    /**
     * @var array<string, mixed>
     */
    #[LiveProp]
    public array $data = [];

    /**
     * @return FormInterface<IpRestrictionSettingsDTO>
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(
            IpRestrictionSettingsType::class,
            $this->ipRestrictionSettingsDTO
        );
    }
}
