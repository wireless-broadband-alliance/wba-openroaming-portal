<?php

namespace App\Twig\Components;

use App\DTO\CapportSettingsDTO;
use App\Enum\SettingName;
use App\Form\CapportSettingsType;
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
        return $this->createForm(CapportSettingsType::class, $this->capportSettingsDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(CapportSettingsType::class, $this->capportSettingsDTO);

        // Submit the current DTO values
        $form->submit([
            SettingName::CAPPORT_ENABLED->value => $this->capportSettingsDTO->capportEnabled,
            SettingName::CAPPORT_PORTAL_URL->value => $this->capportSettingsDTO->capportPortalUrl,
            SettingName::CAPPORT_VENUE_INFO_URL->value => $this->capportSettingsDTO->capportVenueInfoUrl,
        ], false);

        $this->form = $form;
    }
}
