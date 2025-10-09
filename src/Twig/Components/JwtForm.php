<?php

namespace App\Twig\Components;

use App\DTO\JwtDTO;
use App\DTO\SettingsDTO;
use App\Form\JwtType;
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
class JwtForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public JwtDTO|null $jwtDTO = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(JwtType::class, $this->jwtDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        if (!$this->jwtDTO instanceof JwtDTO) {
            $this->jwtDTO = new JwtDTO();
        }

        // Rebuild form with DTO data
        $form = $this->createForm(JwtType::class, $this->jwtDTO);

        // Submit the form data to trigger validation
        $form->submit([
            'jwtSecretKey' => $this->jwtDTO->jwtSecretKey,
            'jwtPublicKey' => $this->jwtDTO->jwtPublicKey,
            'jwtPassphrase' => $this->jwtDTO->jwtPassphrase,
        ], false);

        $this->form = $form;
    }

}