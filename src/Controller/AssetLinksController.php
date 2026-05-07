<?php

namespace App\Controller;

use App\DTO\ReturnAppsSettingsDTO;
use App\Entity\ReturnAppFingerprint;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\OperationMode;
use App\Form\ReturnAppsType;
use App\Repository\ReturnAppFingerprintRepository;
use App\Repository\SettingRepository;
use App\Enum\SettingName;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SettingsService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class AssetLinksController extends AbstractController
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly EventActions $eventActions,
        private readonly GetSettings $getSettings,
        private readonly TranslatorInterface $translator,
        private readonly SettingsService $settingsService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ReturnAppFingerprintRepository $returnAppFingerprintRepository,
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * @throws \JsonException
     */
    #[Route('/.well-known/assetlinks.json', name: 'asset_links_android', methods: ['GET'])]
    public function android(): Response
    {
        $enabledSetting = $this->settingRepository->findOneBy([
            'name' => SettingName::RETURN_APPS_ENABLED->value
        ]);

        $enabled = $enabledSetting?->getValue();

        if ($enabled === OperationMode::OFF->value) {
            return new JsonResponse(
                ['error' => 'Android App Site Association is disabled'],
                Response::HTTP_NOT_FOUND
            );
        }

        $packageName = $this->settingRepository
            ->findOneBy(['name' => SettingName::RETURN_APPS_PACKAGE_NAME_ANDROID->value])
            ?->getValue();

        $fingerprintEntities = $this->returnAppFingerprintRepository->findActiveFingerprints();

        $fingerprints = array_map(
            static fn($fp) => $fp->getName(),
            $fingerprintEntities
        );

        return new JsonResponse([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $packageName,
                    'sha256_cert_fingerprints' => $fingerprints,
                ],
            ],
        ]);
    }


    /**
     * @throws \JsonException
     */
    #[Route('/.well-known/apple-app-site-association', name: 'asset_links_ios', methods: ['GET'])]
    public function ios(): Response
    {
        $enabledSetting = $this->settingRepository->findOneBy([
            'name' => SettingName::RETURN_APPS_ENABLED->value
        ]);

        $enabled = $enabledSetting?->getValue();

        if ($enabled === OperationMode::OFF->value) {
            return new JsonResponse(
                ['error' => 'Apple App Site Association is disabled'],
                Response::HTTP_NOT_FOUND
            );
        }

        $appIds = $this->settingRepository
            ->findOneBy(['name' => SettingName::RETURN_APPS_ID_IOS->value])
            ?->getValue();

        $appIds = $appIds ? [$appIds] : [];

        // Add the corresponding for the app redirection
        $path = ltrim($this->router->generate('app_return_to_app'), '/');
        $components = [
            [
                '/' => '/' . $path,
                'comment' => sprintf('Matches any URL whose path starts with %s', $path),
            ],
        ];

        return new JsonResponse([
            'applinks' => [
                'details' => [
                    [
                        'appIDs' => $appIds,
                        'components' => $components,
                    ],
                ],
            ],
        ]);
    }

    #[Route('/dashboard/settings/returnApps', name: 'admin_dashboard_return_apps')]
    #[IsGranted(UserAuthenticationVoter::RETURN_APPS_MANAGEMENT_READ)]
    public function settingsReturnApps(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();
        $fingerprintEntities = $this->returnAppFingerprintRepository->findActiveFingerprints();
        $fingerprints = array_map(static fn($fp) => $fp->getName(), $fingerprintEntities);

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::RETURN_APPS_MANAGEMENT_WRITE);

        // Initialize DTO from settings
        $dto = new ReturnAppsSettingsDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(
            ReturnAppsType::class,
            $dto,
            [
                'disabled' => !$canWrite,
                'fingerprints' => $fingerprints,
            ]
        );
        $form->handleRequest($request);
        if ($canWrite && $form->isSubmitted() && $form->isValid()) {
            $this->settingsService->updateSettingsFromArray($dto->toArray());
            $submittedValues = $form->get('fingerprints')->getData();

            $currentEntities = $this->returnAppFingerprintRepository->findActiveFingerprints();
            $currentValues = array_map(static fn($fp) => $fp->getName(), $currentEntities);

            $toAdd = array_diff($submittedValues, $currentValues);
            $toRemove = array_diff($currentValues, $submittedValues);

            // Add new fingerprints
            foreach ($toAdd as $value) {
                $entity = new ReturnAppFingerprint();
                $entity->setName($value);
                $entity->setCreatedAt(new DateTimeImmutable());

                $this->entityManager->persist($entity);
            }

            // Soft-delete removed fingerprints
            foreach ($currentEntities as $entity) {
                if (in_array($entity->getName(), $toRemove, true)) {
                    $entity->setDeletedAt(new DateTimeImmutable());
                }
            }

            $this->entityManager->flush();

            // Log the event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::RETURN_APPS_UPDATED->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ]
            );

            $this->addFlash(
                'success',
                $this->translator->trans('newChangesAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_return_apps');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'returnAppsSettingsDTO' => $dto,
            'data' => $data,
            'user' => $currentUser,
        ]);
    }
}
