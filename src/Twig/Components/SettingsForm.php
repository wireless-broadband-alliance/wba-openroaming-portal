<?php

namespace App\Twig\Components;

use App\DTO\DbSetupDTO;
use App\DTO\SettingsDTO;
use App\Form\DbSetupType;
use App\Form\SettingsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
class SettingsForm extends AbstractController
{

    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public SettingsDTO|null $settingsDTO = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(SettingsType::class, $this->settingsDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        if (!$this->settingsDTO instanceof SettingsDTO) {
            $this->settingsDTO = new SettingsDTO();
        }

        // Rebuild form with DTO data
        $form = $this->createForm(SettingsType::class, $this->settingsDTO);

        // Submit the form data to trigger validation
        $form->submit([
            'trustedProxies' => $this->settingsDTO->trustedProxies,
            'turnstileKey' => $this->settingsDTO->turnstileKey,
            'turnstileSecret' => $this->settingsDTO->turnstileSecret,
            'jwtSecretKey' => $this->settingsDTO->jwtSecretKey,
            'jwtPublicKey' => $this->settingsDTO->jwtPublicKey,
            'jwtPassphrase' => $this->settingsDTO->jwtPassphrase,
        ], false);

        $this->form = $form;
    }

}