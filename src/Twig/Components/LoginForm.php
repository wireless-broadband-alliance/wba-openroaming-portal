<?php

namespace App\Twig\Components;

use App\DTO\LoginChoiceDTO;
use App\Form\LoginType;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class LoginForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public LoginChoiceDTO|null $loginChoiceDTO = null;

    #[LiveProp]
    public array $defaultRegions = ['PT', 'US', 'GB']; // default phone regions

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(LoginType::class, $this->loginChoiceDTO, [
            'region_inputs' => $this->defaultRegions,
        ]);
    }

    #[LiveAction]
    public function validate(): void
    {
        if (!$this->loginChoiceDTO) {
            $this->loginChoiceDTO = new LoginChoiceDTO();
        }

        // Transform phone number string into PhoneNumber object
        if (is_string($this->loginChoiceDTO->phoneNumber) && !empty($this->loginChoiceDTO->phoneNumber)) {
            try {
                $phoneUtil = PhoneNumberUtil::getInstance();
                $this->loginChoiceDTO->phoneNumber = $phoneUtil->parse($this->loginChoiceDTO->phoneNumber, 'US');
            } catch (NumberParseException) {
                $this->loginChoiceDTO->phoneNumber = null;
            }
        }

        // Rebuild form with DTO data
        $form = $this->createForm(LoginType::class, $this->loginChoiceDTO, [
            'region_inputs' => $this->defaultRegions,
        ]);

        // Submit the form data to trigger validation
        $form->submit([
            'loginMethod' => $this->loginChoiceDTO->loginMethod,
            'email' => $this->loginChoiceDTO->email,
            'phoneNumber' => $this->loginChoiceDTO->phoneNumber,
            'password' => $this->loginChoiceDTO->password,
        ], false);

        $this->form = $form;
    }

    #[LiveAction]
    public function changeLoginMethod(string $method): void
    {
        if (!$this->loginChoiceDTO) {
            $this->loginChoiceDTO = new LoginChoiceDTO();
        }

        $this->loginChoiceDTO->loginMethod = $method;

        dd('Radio clicked! Current method: ' . $this->loginChoiceDTO->loginMethod);
    }
}
