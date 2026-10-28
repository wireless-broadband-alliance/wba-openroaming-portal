<?php

namespace App\Twig\Components;

use App\DTO\NetworkDTO;
use App\Entity\Network;
use App\Form\CreateNetworkType;
use App\Repository\AccessPointRepository;
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
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;

#[AsLiveComponent]
final class ManageNetworksForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use LiveCollectionTrait;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly AccessPointRepository $accessPointRepository
    ) {
    }

    #[LiveProp(writable: ['name', 'description', 'geometryJson'])]
    public NetworkDTO|null $networkDTO = null;

    /** @var array<string, array{value: ?string, description?: ?string}>|null */
    #[LiveProp]
    public ?array $data = null;

    #[LiveProp]
    public Network|null $network = null;

    /**
     * @return FormInterface<mixed>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        $canWrite = $this->isGranted(UserAuthenticationVoter::MAP_WRITE);

        $form = $this->createForm(CreateNetworkType::class, $this->networkDTO, ['disabled' => !$canWrite]);

        $currentRequest = $this->requestStack->getCurrentRequest();
        $isLiveRequest = $currentRequest instanceof Request && $currentRequest->headers->has('X-Live-Component-Action');

        if ($isLiveRequest && $form->isSubmitted() === false) {
            $form->submit([], false);
        }

        foreach ($form->getErrors() as $error) {
            if (
                $error->getCause() &&
                $form->has('geometryJson') &&
                $error->getCause()->getPropertyPath() === 'data.geometryJson'
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

    /**
     * @return array<int, array{id: mixed, name: string, lat: float, lng: float}>
     */
    public function getValidAccessPoints(): array
    {
        if (!$this->network instanceof \App\Entity\Network || !$this->network->getId()) {
            return [];
        }

        $accessPointsForMap = $this->accessPointRepository
            ->createQueryBuilder('ap')
            ->select('ap.id', 'ap.name', 'ap.location')
            ->where('ap.network = :network')
            ->andWhere('ap.location IS NOT NULL')
            ->setParameter('network', $this->network)
            ->getQuery()
            ->getArrayResult();

        $validAps = [];

        foreach ($accessPointsForMap as $apData) {
            $locationJson = $apData['location'];
            $location = is_string($locationJson) ? json_decode($locationJson, true) : $locationJson;

            if (is_array($location) && isset($location['coordinates'][0], $location['coordinates'][1])) {
                $lng = (float)$location['coordinates'][0];
                $lat = (float)$location['coordinates'][1];

                if ($lat !== 0.0 || $lng !== 0.0) {
                    $validAps[] = [
                        'id' => $apData['id'] ?? null,
                        'name' => $apData['name'] ?? 'Access Point',
                        'lat' => $lat,
                        'lng' => $lng,
                    ];
                }
            }
        }

        return $validAps;
    }

    public function getMap(): Map
    {
        $map = new Map()
            ->center(new Point(37.7412, -25.6756))
            ->zoom(13);

        foreach ($this->getValidAccessPoints() as $ap) {
            $map->addMarker(new Marker(
                position: new Point($ap['lat'], $ap['lng']),
                title: $ap['name']
            ));
        }

        return $map;
    }
}
