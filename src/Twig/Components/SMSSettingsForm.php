<?php

namespace App\Twig\Components;

use App\DTO\SMSSettingsDTO;
use App\Form\SMSSettingsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class SMSSettingsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public SMSSettingsDTO|null $SMSSettingsDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(SMSSettingsType::class, $this->SMSSettingsDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(SMSSettingsType::class, $this->SMSSettingsDTO);

        // Submit the current DTO values
        $form->submit([
            'capportEnabled' => $this->capportSettingsDTO?->capportEnabled,
            'capportPortalUrl' => $this->capportSettingsDTO?->capportPortalUrl,
            'capportVenueInfoUrl' => $this->capportSettingsDTO?->capportVenueInfoUrl,
        ], false);

        $this->form = $form;
    }
}
