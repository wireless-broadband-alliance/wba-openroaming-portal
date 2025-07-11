<?php

namespace App\Twig\Components;

use App\DTO\ScheduleDTO;
use App\Enum\OperationMode;
use App\Form\ScheduleType;
use App\Repository\SettingRepository;
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

    #[LiveProp]
    public bool|null $default_use_advanced_mode = false;

    public function __construct(private readonly SettingRepository $settingRepository)
    {
        $cronAdvanceStatus = $settingRepository->findOneBy(["name" => "CRON_ADVANCED_STATUS"]);
        if (!is_null($cronAdvanceStatus)) {
            $this->default_use_advanced_mode = $cronAdvanceStatus->getValue() === OperationMode::ON->value;
        }
    }

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(ScheduleType::class, $this->scheduleDTO);
    }
}
