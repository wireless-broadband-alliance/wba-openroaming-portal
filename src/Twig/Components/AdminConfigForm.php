<?php

namespace App\Twig\Components;

use App\DTO\AdminConfigDTO;
use App\Form\AdminConfigType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class AdminConfigForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public AdminConfigDTO|null $adminConfigDTO = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(AdminConfigType::class, $this->adminConfigDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        if (!$this->adminConfigDTO instanceof AdminConfigDTO) {
            $this->adminConfigDTO = new AdminConfigDTO();
        }

        // Rebuild form with DTO data
        $form = $this->createForm(AdminConfigType::class, $this->adminConfigDTO);


        // Submit the form data to trigger validation
        $form->submit([
            'email' => $this->adminConfigDTO->email,
            'password' => $this->adminConfigDTO->password,
            'confirmPassword' => $this->adminConfigDTO->confirmPassword,
        ], false);

        $this->form = $form;
    }
}
