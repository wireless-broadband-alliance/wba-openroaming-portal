<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\DTO\MapSettingsDTO;
use App\Form\MapSettingsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Point;

#[AsLiveComponent]
final class MapSettingsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    #[LiveProp]
    public ?MapSettingsDTO $mapSettingsDTO = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {

        $form = $this->createForm(MapSettingsType::class, $this->mapSettingsDTO);

        $currentRequest = $this->requestStack->getCurrentRequest();
        $isLiveRequest = $currentRequest instanceof Request && $currentRequest->headers->has('X-Live-Component-Action');

        if ($isLiveRequest && $form->isSubmitted() === false) {
            $form->submit([], false);
        }

        return $form;
    }

    /**
     * @return FormErrorIterator<FormError>
     */
    public function getFormErrors(): FormErrorIterator
    {
        return $this->getForm()->getErrors(true);
    }

    public function getMap(): Map
    {
        $lat = 39.3999;
        $lng = -8.2245;
        $zoom = 6;

        if ($this->mapSettingsDTO instanceof MapSettingsDTO) {
            if ($this->mapSettingsDTO->latitude !== null && $this->mapSettingsDTO->longitude !== null) {
                $lat = (float) $this->mapSettingsDTO->latitude;
                $lng = (float) $this->mapSettingsDTO->longitude;
            }
            $zoom = $this->mapSettingsDTO->zoom;
        }

        return new Map()
            ->center(new Point($lat, $lng))
            ->zoom($zoom);
    }
}
