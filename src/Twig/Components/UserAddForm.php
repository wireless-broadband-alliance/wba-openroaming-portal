<?php

namespace App\Twig\Components;

use App\DTO\UserAddDTO;
use App\Form\UserAddType;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class UserAddForm extends AbstractController
{
  use ComponentWithFormTrait;
  use DefaultActionTrait;

  #[LiveProp]
  public UserAddDTO|null $userAddDTO = null;

  #[LiveProp]
  public string|null $rawPhoneNumber = null;

  /**
   * @return FormInterface<mixed>
   */
  protected function instantiateForm(): FormInterface
  {
    return $this->createForm(UserAddType::class, $this->userAddDTO);
  }

  #[LiveAction]
  public function validate(): void
  {
    // Parse the raw phone number string into a PhoneNumber object
    if (!in_array($this->rawPhoneNumber, [null, '', '0'], true)) {
      try {
        $phoneUtil = PhoneNumberUtil::getInstance();
        $this->userAddDTO->phoneNumber = $phoneUtil->parse(
            $this->rawPhoneNumber,
            'US'
        );
      } catch (NumberParseException) {
        $this->userAddDTO->phoneNumber = null;
      }
    }

    $form = $this->createForm(UserAddType::class, $this->userAddDTO);

    $submitData = [
        'accountType' => $this->userAddDTO->accountType,
        'email' => $this->userAddDTO->email,
        'phoneNumber' => $this->userAddDTO->phoneNumber,
        'firstName' => $this->userAddDTO->firstName,
        'lastName' => $this->userAddDTO->lastName,
        'password' => $this->userAddDTO->password ?? null,
    ];

    if (property_exists($this->userAddDTO, 'roles')) {
      $submitData['roles'] = $this->userAddDTO->roles;
    }

    $form->submit($submitData, false);

    $this->form = $form;
  }
}
