<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\DTO\IpRestrictionSettingsDTO;
use App\Form\IpRestrictionSettingsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class IpRestrictionSettingsForm extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    #[LiveProp(writable: true)]
    public ?IpRestrictionSettingsDTO $ipRestrictionSettingsDTO = null;

    /**
     * @var array<string, mixed>
     */
    #[LiveProp]
    public array $data = [];

    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(
            IpRestrictionSettingsType::class,
            $this->ipRestrictionSettingsDTO
        );
    }

    #[LiveAction]
    public function addAllowedIp(): void
    {
        $this->ipRestrictionSettingsDTO->allowedIps[] = '';
    }

    #[LiveAction]
    public function removeAllowedIp(#[LiveArg] int $index): void
    {
        unset($this->ipRestrictionSettingsDTO->allowedIps[$index]);
        $this->ipRestrictionSettingsDTO->allowedIps = array_values($this->ipRestrictionSettingsDTO->allowedIps);
    }
}
