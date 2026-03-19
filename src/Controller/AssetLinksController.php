<?php

namespace App\Controller;

use App\DTO\ReturnAppsSettingsDTO;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Form\ReturnAppsType;
use App\Repository\SettingRepository;
use App\Enum\SettingName;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\SettingsService;
use DateTime;
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
        private SettingRepository $settingRepository,
        private RouterInterface $router,
        private EventActions $eventActions,
        private GetSettings $getSettings,
        private TranslatorInterface $translator,
        private SettingsService $settingsService,
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

        if (!$enabled) {
            return new JsonResponse(
                ['error' => 'Asset links are currently disabled'],
                Response::HTTP_NOT_FOUND
            );
        }

        $packageName = $this->settingRepository
            ->findOneBy(['name' => SettingName::RETURN_APPS_PACKAGE_NAME->value])
            ?->getValue();

        $fingerprints = $this->settingRepository
            ->findOneBy(['name' => SettingName::RETURN_APPS_FINGERPRINTS->value])
            ?->getValue();

        $fingerprints = is_string($fingerprints)
            ? json_decode($fingerprints, true, 512, JSON_THROW_ON_ERROR) ?? []
            : (array)$fingerprints;

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
        if (!$enabled) {
            return new JsonResponse(
                ['error' => 'Apple App Site Association is disabled'],
                Response::HTTP_NOT_FOUND
            );
        }

        $appIds = $this->settingRepository
            ->findOneBy(['name' => SettingName::RETURN_APPS_PACKAGE_NAME->value])
            ?->getValue();

        $path = $this->router->generate(
            'app_api_landing',
            [],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );
        // Add the corresponding for the app redirection
        $components = [
            [
                '/' => $path,
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
    public function settingsTwoFA(Request $request): Response
    {
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::RETURN_APPS_MANAGEMENT_WRITE);

        // Initialize DTO from settings
        $dto = new ReturnAppsSettingsDTO($data);

        // Create form bound to DTO
        $form = $this->createForm(ReturnAppsType::class, $dto, ['disabled' => !$canWrite]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $canWrite) {
            // Save updated settings
            $this->settingsService->updateSettingsFromArray($dto->toArray());
            $this->settingsService->flush();

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