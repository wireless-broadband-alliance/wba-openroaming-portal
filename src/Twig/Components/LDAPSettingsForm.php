<?php

namespace App\Twig\Components;

use App\DTO\LDAPSettingsDTO;
use App\Form\LDAPType;
use App\Entity\Setting;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class LDAPSettingsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public LDAPSettingsDTO|null $ldapSettingsDTO = null;

    /** @var Setting[] */
    public array $settings = [];

    public function __construct(
        private readonly SettingsService $settingsService
    ) {
    }

    /**
     * @return FormInterface<mixed>
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(LDAPType::class, $this->ldapSettingsDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(LDAPType::class, $this->ldapSettingsDTO);
        $form->submit([
            'syncLdapEnabled' => $this->ldapSettingsDTO->syncLdapEnabled,
            'syncLdapServer' => $this->ldapSettingsDTO->syncLdapServer,
            'syncLdapBindUserDn' => $this->ldapSettingsDTO->syncLdapBindUserDn,
            'syncLdapBindUserPassword' => $this->ldapSettingsDTO->syncLdapBindUserPassword,
            'syncLdapSearchBaseDn' => $this->ldapSettingsDTO->syncLdapSearchBaseDn,
            'syncLdapSearchFilter' => $this->ldapSettingsDTO->syncLdapSearchFilter,
        ], false);

        $this->form = $form;
    }

    #[LiveAction]
    public function save(): void
    {
        $this->ldapSettingsDTO->updateSettings($this->settingsByName());
        $this->settingsService->flush();
    }

    private function settingsByName(): array
    {
        $map = [];
        foreach ($this->settings as $setting) {
            $map[$setting->getName()] = $setting;
        }
        return $map;
    }
}
