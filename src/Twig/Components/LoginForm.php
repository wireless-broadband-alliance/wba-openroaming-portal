<?php

namespace App\Twig\Components;

use App\DTO\LoginChoiceDTO;
use App\Enum\UserProvider;
use App\Form\LoginType;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class LoginForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public LoginChoiceDTO|null $loginChoiceDTO = null;

    #[LiveProp(writable: true)]
    public string $loginMethod = '';

    #[LiveProp(writable: true)]
    public ?string $email = null;

    #[LiveProp(writable: true)]
    public ?PhoneNumber $phoneNumber = null;

    #[LiveProp(writable: true)]
    public ?string $password = null;

    #[LiveProp]
    public array $defaultRegions = ['PT', 'US', 'GB']; // default phone regions

    /**
     * Instantiate the form and sync LiveProps.
     */
    protected function instantiateForm(): FormInterface
    {
        if (!$this->loginChoiceDTO instanceof LoginChoiceDTO) {
            $this->loginChoiceDTO = new LoginChoiceDTO();
        }

        // Create the form with region inputs
        $form = $this->createForm(LoginType::class, $this->loginChoiceDTO, [
            'region_inputs' => $this->defaultRegions,
        ]);

        // Sync LiveProps from DTO
        $this->loginMethod = $this->loginChoiceDTO->loginMethod ?? $this->loginMethod;
        $this->email = $this->loginChoiceDTO->email;
        $this->phoneNumber = $this->loginChoiceDTO->phoneNumber;
        $this->password = $this->loginChoiceDTO->password;

        return $form;
    }

    /**
     * Handle login method switching (EMAIL / PHONE_NUMBER)
     */
    #[LiveAction]
    public function changeLoginMethod(string $method): void
    {
        $this->loginMethod = $method;
        $this->loginChoiceDTO->loginMethod = $method;

        // Reset other fields when switching
        if ($method === UserProvider::EMAIL->value) {
            $this->phoneNumber = null;
            $this->loginChoiceDTO->phoneNumber = null;
        } else {
            $this->email = null;
            $this->loginChoiceDTO->email = null;
        }

        // Rebuild the form with updated DTO and default regions
        $this->form = $this->createForm(LoginType::class, $this->loginChoiceDTO, [
            'region_inputs' => $this->defaultRegions,
        ]);
    }

    #[LiveAction]
    public function validate(): void
    {
        $this->loginChoiceDTO->email = $this->email;
        $this->loginChoiceDTO->password = $this->password;
        $this->loginChoiceDTO->loginMethod = $this->loginMethod;

        // Transform string phone number to PhoneNumber object if not null
        if ($this->phoneNumber instanceof PhoneNumber) {
            $phoneUtil = PhoneNumberUtil::getInstance();
            try {
                $this->loginChoiceDTO->phoneNumber = $phoneUtil->parse($this->phoneNumber, 'US');
            } catch (NumberParseException) {
                $this->loginChoiceDTO->phoneNumber = null; // will trigger NotNull + PhoneNumber validation
            }
        } else {
            $this->loginChoiceDTO->phoneNumber = null;
        }

        $form = $this->createForm(LoginType::class, $this->loginChoiceDTO, [
            'region_inputs' => $this->defaultRegions,
        ]);

        $form->submit([
            'loginMethod' => $this->loginMethod,
            'email' => $this->email,
            'phoneNumber' => $this->phoneNumber,
            'password' => $this->password,
        ], false);

        $this->form = $form;
    }
}
