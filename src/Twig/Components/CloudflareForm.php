<?php

namespace App\Twig\Components;

use App\DTO\CloudflareDTO;
use App\DTO\DbSetupDTO;
use App\Form\CloudflareType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
final class CloudflareForm extends AbstractController
{

    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public CloudflareDTO|null $cloudflareDTO = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {

        return $this->createForm(CloudflareType::class, $this->cloudflareDTO);
    }

    #[LiveAction]
    public function validate(): void
    {
        if (!$this->cloudflareDTO instanceof DbSetupDTO) {
            $this->cloudflareDTO = new CloudflareDTO();
        }

        $this->form = $this->createForm(CloudflareType::class, $this->cloudflareDTO);
    }
}