<?php

namespace App\Twig\Components;

use App\DTO\ScheduleDTO;
use App\Form\ScheduleType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class ScheduleForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public ScheduleDTO|null $scheduleDTO = null;

    #[LiveProp]
    public bool|null $deleteUnconfirmedWarning = null;

    #[LiveProp]
    public bool|null $profileExpiredWarning = null;

    #[LiveProp]
    public bool|null $ldapCronWarning = null;


    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(ScheduleType::class, $this->scheduleDTO);
    }
}
