<?php

namespace App\Twig\Components;

use App\DTO\SMSSettingsDTO;
use App\Enum\SettingName;
use App\Form\SMSSettingsType;
use App\Security\Voter\UserAuthenticationVoter;
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
    public ?SMSSettingsDTO $SMSSettingsDTO = null;

    /**
     * Raw settings data fetched from database or service.
     * @var array<string, array{value: ?string, description?: ?string}>|null
     */
    #[LiveProp]
    public ?array $data = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        $canWrite = $this->isGranted(UserAuthenticationVoter::SMS_CONFIG_WRITE);

        return $this->createForm(SMSSettingsType::class, $this->SMSSettingsDTO, ['disabled' => !$canWrite]);
    }

    #[LiveAction]
    public function validate(): void
    {
        // Create form manually to trigger validation
        $form = $this->createForm(SMSSettingsType::class, $this->SMSSettingsDTO);

        $regions = $this->SMSSettingsDTO->defaultRegionPhoneInputs ?? [];

        // Submit current DTO data (manual mapping)
        $form->submit([
            SettingName::DEFAULT_REGION_PHONE_INPUTS->value => $regions,
        ], false);

        // Store validated form
        $this->form = $form;
    }
}
