<?php

namespace App\Twig\Components;

use App\DTO\AccessPointDTO;
use App\Entity\AccessPoint;
use App\Entity\Network;
use App\Form\CreateAccessPointType;
use App\Security\Voter\UserAuthenticationVoter;
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
use Symfony\UX\LiveComponent\LiveCollectionTrait;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Point;

#[AsLiveComponent]
final class ManageAccessPointsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    #[LiveProp]
    public AccessPointDTO|null $accessPointDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    #[LiveProp]
    public Network|null $network = null;

    #[LiveProp]
    public AccessPoint|null $accessPoint = null;

    /** @var array<int, array{lat: float, lng: float, name: ?string}> */
    #[LiveProp]
    public array $otherAccessPoints = [];

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        $canWrite = $this->isGranted(UserAuthenticationVoter::MAP_WRITE);


        $form = $this->createForm(CreateAccessPointType::class, $this->accessPointDTO, ['disabled' => !$canWrite]);

        $currentRequest = $this->requestStack->getCurrentRequest();
        $isLiveRequest = $currentRequest instanceof Request && $currentRequest->headers->has('X-Live-Component-Action');

        if ($isLiveRequest && $form->isSubmitted() === false) {
            $form->submit([], false);
        }

        foreach ($form->getErrors() as $error) {
            if (
                $error->getCause() &&
                $error->getCause()->getPropertyPath() === 'data.geometryJson' &&
                $form->has('geometryJson')
            ) {
                $form->get('geometryJson')->addError(new FormError($error->getMessage()));
            }
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
        return new Map()
            ->center(new Point(37.7412, -25.6756))
            ->zoom(13);
    }
}
