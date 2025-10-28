<?php

namespace App\Twig\Components;

use App\DTO\RadiusSettingsDTO;
use App\Enum\SettingName;
use App\Form\RadiusType;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class RadiusSettingsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public RadiusSettingsDTO|null $radiusSettingsDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(RadiusType::class, $this->radiusSettingsDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(RadiusType::class, $this->radiusSettingsDTO);

        $form->submit([
            SettingName::DISPLAY_NAME->value =>
                $this->radiusSettingsDTO->displayName,
            SettingName::RADIUS_REALM_NAME->value =>
                $this->radiusSettingsDTO->radiusRealmName,
            SettingName::DOMAIN_NAME->value =>
                $this->radiusSettingsDTO->domainName,
            SettingName::OPERATOR_NAME->value =>
                $this->radiusSettingsDTO->operatorName,
            SettingName::RADIUS_TLS_NAME->value =>
                $this->radiusSettingsDTO->radiusTlsName,
            SettingName::NAI_REALM->value =>
                $this->radiusSettingsDTO->naiRealm,
            SettingName::RADIUS_TRUSTED_ROOT_CA_SHA1_HASH->value =>
                $this->radiusSettingsDTO->radiusTrustedRootCaSha1Hash,
            SettingName::PAYLOAD_IDENTIFIER->value =>
                $this->radiusSettingsDTO->payloadIdentifier,
            SettingName::PROFILES_ENCRYPTION_TYPE_IOS_ONLY->value =>
                $this->radiusSettingsDTO->profilesEncryptionTypeIosOnly,
        ], false);

        $this->form = $form;
    }
}
