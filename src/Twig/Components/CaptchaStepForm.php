<?php

namespace App\Twig\Components;

use App\DTO\CaptchaDTO;
use App\Form\CaptchaStepType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
class CaptchaStepForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public ?CaptchaDTO $captchaDTO = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(CaptchaStepType::class, $this->captchaDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        if (!$this->captchaDTO instanceof CaptchaDTO) {
            $this->captchaDTO = new CaptchaDTO();
        }

        $this->form = $this->createForm(CaptchaStepType::class, $this->captchaDTO);
    }
}
