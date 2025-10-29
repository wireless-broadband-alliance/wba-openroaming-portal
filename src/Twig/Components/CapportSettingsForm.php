<?php

namespace App\Twig\Components;

use App\DTO\CapportSettingsDTO;
use App\Form\CapportType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class CapportSettingsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public CapportSettingsDTO|null $capportSettingsDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(CapportType::class, $this->capportSettingsDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(CapportType::class, $this->capportSettingsDTO);

        // Submit the current DTO values
        $form->submit([
            'capportEnabled' => $this->capportSettingsDTO?->capportEnabled,
            'capportPortalUrl' => $this->capportSettingsDTO?->capportPortalUrl,
            'capportVenueInfoUrl' => $this->capportSettingsDTO?->capportVenueInfoUrl,
        ], false);

        $this->form = $form;
    }
}
