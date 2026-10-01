<?php

namespace App\Twig\Components;

use App\DTO\SecurityTxtSettingsDTO;
use App\Form\SecurityTxtSettingsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class SecurityTxtSettingsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public SecurityTxtSettingsDTO|null $securityTxtSettingsDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        $canWrite = $this->isGranted('ROLE_SUPER_ADMIN');

        return $this->createForm(
            SecurityTxtSettingsType::class,
            $this->securityTxtSettingsDTO,
            ['disabled' => !$canWrite]
        );
    }

    #[LiveAction]
    public function validate(): void
    {
        $form = $this->createForm(SecurityTxtSettingsType::class, $this->securityTxtSettingsDTO);

        $form->submit([
            'securityContact' => $this->securityTxtSettingsDTO?->securityContact,
            'securityExpires' => $this->securityTxtSettingsDTO?->securityExpires?->format('Y-m-d'),
            'securityPgpFingerprint' => $this->securityTxtSettingsDTO?->securityPgpFingerprint,
        ], false);

        $this->form = $form;
    }
}
