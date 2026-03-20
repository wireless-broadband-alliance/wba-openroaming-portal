<?php

namespace App\Twig\Components;

use App\DTO\ReturnAppsSettingsDTO;
use App\Enum\SettingName;
use App\Form\ReturnAppsType;
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
final class ReturnAppsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public ReturnAppsSettingsDTO|null $returnAppsSettingsDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        $canWrite = $this->isGranted(UserAuthenticationVoter::RETURN_APPS_MANAGEMENT_WRITE);

        return $this->createForm(ReturnAppsType::class, $this->returnAppsSettingsDTO, ['disabled' => !$canWrite]);
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(ReturnAppsType::class, $this->returnAppsSettingsDTO);

        $form->submit([
            SettingName::RETURN_APPS_ENABLED->value =>
                $this->returnAppsSettingsDTO->returnAppsEnabled,
            SettingName::RETURN_APPS_PACKAGE_NAME_ANDROID->value =>
                $this->returnAppsSettingsDTO->returnAppsPackageNameAndroid,
            SettingName::RETURN_APPS_ID_IOS->value =>
                $this->returnAppsSettingsDTO->returnAppsIdIOS,
        ], false);

        $this->form = $form;
    }
}