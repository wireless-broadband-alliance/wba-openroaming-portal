<?php

namespace App\Controller;

use App\Entity\SamlProvider;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Enum\UserProvider;
use App\Enum\UserRadiusProfileRevokeReason;
use App\Enum\UserRadiusProfileStatus;
use App\Form\SamlProviderExtraOptionsType;
use App\Form\SamlProviderType;
use App\Repository\SamlProviderRepository;
use App\Repository\SettingRepository;
use App\Repository\UserExternalAuthRepository;
use App\Repository\UserRepository;
use App\Service\AuthSamlMethodCheckerService;
use App\Service\CertificateService;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\ProfileManager;
use App\Service\SamlProviderDeletionService;
use App\Service\SamlProviderResolverService;
use App\Service\UserDeletionService;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use OneLogin\Saml2\Error;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SamlProviderController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly settingRepository $settingRepository,
        private readonly GetSettings $getSettings,
        private readonly SamlProviderRepository $samlProviderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
        private readonly UserExternalAuthRepository $userExternalAuthRepository,
        private readonly ProfileManager $profileManager,
        private readonly UserDeletionService $userDeletionService,
        private readonly SamlProviderDeletionService $samlProviderDeletionService,
        private readonly SamlProviderResolverService $samlProviderResolverService,
        private readonly AuthSamlMethodCheckerService $authSamlMethodCheckerService,
        private readonly CertificateService $certificateService,
    ) {
    }

    /**
     * @throws Error
     */
    #[Route('/saml', name: 'app_saml_login')]
    public function getStuff(Request $request): RedirectResponse
    {
        $samlProviderId = $request->get('saml_provider_id');
        if (!$samlProviderId) {
            throw $this->createNotFoundException('Missing SAML provider identifier.');
        }
        $samlProvider = $this->samlProviderResolverService->authSamlProviderById($samlProviderId);
        $url = $this->generateUrl('saml_login', [
            'samlProvider' => $samlProvider,
        ]);

        return new RedirectResponse($url);
    }

    /**
     * @throws Exception
     */
    #[Route('/dashboard/saml-provider', name: 'admin_dashboard_saml_provider')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        Request $request,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] string $sort = 'createdAt',
        #[MapQueryParameter] string $order = 'desc',
        #[MapQueryParameter] ?int $count = 7,
        #[MapQueryParameter] ?string $filter = 'all',
    ): Response {
        // Retrieve settings for rendering in the template
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Validate the $count parameter
        if (!is_int($count) || $count <= 0) {
            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        $searchTerm = $request->query->get('s');
        // Fetch the filtered, sorted, and paginated providers using the repository
        $paginator = $this->samlProviderRepository->searchWithFilters(
            $filter,
            $searchTerm,
            $sort,
            $order,
            $page,
            $count
        );

        // Retrieve SAML Provider results for the current page
        $samlProviders = iterator_to_array($paginator->getIterator());

        // Count the total number of SAML Providers
        $totalProviders = $this->samlProviderRepository->countSamlProviders($searchTerm, $filter);
        $activeProvidersCount = $this->samlProviderRepository->countSamlProviders($searchTerm, 'active');
        $inactiveProvidersCount = $this->samlProviderRepository->countSamlProviders($searchTerm, 'inactive');

        // Calculate the total number of pages
        $perPage = $count;
        $totalPages = ceil($totalProviders / $perPage);

        return $this->render('admin/saml_provider.html.twig', [
            'data' => $data,
            'samlProviders' => $samlProviders,
            'current_user' => $this->getUser(),
            'totalProviders' => $totalProviders,
            'currentPage' => $page,
            'count' => $count,
            'activeSort' => $sort,
            'activeOrder' => $order,
            'filter' => $filter,
            'searchTerm' => $searchTerm,
            'allProvidersCount' => $totalProviders,
            'activeProviderCount' => $activeProvidersCount,
            'inactiveProvidersCount' => $inactiveProvidersCount,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/dashboard/saml-provider/new', name: 'admin_dashboard_saml_provider_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function addSamlProvider(Request $request): Response
    {
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        $samlProvider = new SamlProvider();
        $formSamlProvider = $this->createForm(SamlProviderType::class, $samlProvider);
        $formSamlProvider->handleRequest($request);

        if ($formSamlProvider->isSubmitted() && $formSamlProvider->isValid()) {
            $samlProvider->setActive(true)
                ->setCreatedAt(new DateTime())
                ->setUpdatedAt(new DateTime());

            // If LDAP is active, log the LDAP-specific event
            if ($samlProvider->getIsLDAPActive() === true) {
                $eventMetaData = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'platform' => PlatformMode::LIVE->value,
                    'ldapCredentialAdded' => $samlProvider->getLdapServer(),
                    'by' => $currentUser->getUuid(),
                ];

                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::ADMIN_ADDED_LDAP_CREDENTIAL->value,
                    new DateTime(),
                    $eventMetaData
                );
            }

            // Persist the new SAML Provider & Save to DB
            $this->entityManager->persist($samlProvider);
            $this->entityManager->flush();

            // Log the general addition of the new SAML Provider
            $eventMetaData = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'platform' => PlatformMode::LIVE->value,
                'samlProviderAdded' => $samlProvider->getName(),
                'by' => $currentUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::ADMIN_ADDED_SAML_PROVIDER->value,
                new DateTime(),
                $eventMetaData
            );

            // Show a success flash message and redirect
            $this->addFlash('success_admin', 'SAML Provider added successfully.');

            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        // Render the form if not submitted or invalid
        return $this->render('admin/shared/saml_providers/_saml_provider_form.html.twig', [
            'formSamlProvider' => $formSamlProvider->createView(),
            'data' => $data,
            'current_user' => $currentUser,
        ]);
    }

    #[Route('/dashboard/saml-provider/edit/{id}', name: 'admin_dashboard_saml_provider_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function editSamlProvider(
        int $id,
        Request $request,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Find the SAML Provider by ID
        $samlProvider = $this->samlProviderRepository->findOneBy(['id' => $id]);
        if (!$samlProvider) {
            $this->addFlash('error_admin', 'This SAML Provider doesn\'t exist!');

            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        // Capture all the required fields before any changes are made
        $originalServer = $samlProvider->getLdapServer();
        $originalPassword = $samlProvider->getLdapBindUserPassword();
        $originalIsLdapActive = $samlProvider->getIsLdapActive();

        $formSamlProvider = $this->createForm(SamlProviderType::class, $samlProvider);
        $formSamlProvider->handleRequest($request);

        if ($formSamlProvider->isSubmitted() && $formSamlProvider->isValid()) {
            // Check if isLDAPActive has changed
            if ($samlProvider->getIsLDAPActive() !== $originalIsLdapActive) {
                $samlProvider->setLdapUpdatedAt(new DateTime());
                $this->entityManager->persist($samlProvider);

                $eventMetaData = [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'platform' => PlatformMode::LIVE->value,
                    'oldLdapCredential' => $originalServer,
                    'ldapCredentialEdited' => $samlProvider->getLdapServer(),
                    'by' => $currentUser->getUuid(),
                ];

                // Save the activation/deactivation event
                $this->eventActions->saveEvent(
                    $currentUser,
                    AnalyticalEventType::ADMIN_EDITED_LDAP_CREDENTIAL->value,
                    new DateTime(),
                    $eventMetaData
                );
            }
            $formPassword = $formSamlProvider->get('ldapBindUserPassword')->getData();

            // If the password field is empty, restore the original value
            if (in_array(trim((string)$formPassword), ['', '0'], true)) {
                $samlProvider->setLdapBindUserPassword($originalPassword);
            }

            $samlProvider->setUpdatedAt(new DateTime());
            $this->entityManager->persist($samlProvider);
            $this->entityManager->flush();

            // Log the SAML Provider edit
            $eventMetaData = [
                'platform' => PlatformMode::LIVE->value,
                'samlProviderEdited' => $samlProvider->getName(),
                'ip' => $request->getClientIp(),
                'by' => $currentUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::ADMIN_EDITED_SAML_PROVIDER->value,
                new DateTime(),
                $eventMetaData
            );

            $this->addFlash(
                'success_admin',
                sprintf('"%s" has been updated successfully.', $samlProvider->getName())
            );

            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        return $this->render('admin/shared/saml_providers/_saml_provider_form.html.twig', [
            'formSamlProvider' => $formSamlProvider->createView(),
            'data' => $data,
            'current_user' => $currentUser,
            'samlProvider' => $samlProvider,
        ]);
    }

    #[Route(
        '/dashboard/saml-provider/edit/extra-options/{id}',
        name: 'admin_dashboard_saml_provider_edit_extra_options'
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function editSAMLExtraOptions(int $id, Request $request): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository);

        // Find the SAML Provider by ID
        $samlProvider = $this->samlProviderRepository->findOneBy(['id' => $id]);
        if (!$samlProvider) {
            $this->addFlash('error_admin', 'This SAML Provider doesn\'t exist!');
            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        $certificatePath = $this->getParameter('kernel.project_dir') . '/signing-keys/cert.pem';
        $certificateLimitDate = strtotime(
            (string)$this->certificateService->getCertificateExpirationDate($certificatePath)
        );
        $realTime = time();
        $timeLeft = round(($certificateLimitDate - $realTime) / (86400)) - 1;
        $profileLimitDate = ((int)$timeLeft);
        if ($profileLimitDate < 0) {
            $profileLimitDate = 0;
        }

        $defaultTimeZone = date_default_timezone_get();
        $dateTime = new DateTime()
            ->setTimestamp($certificateLimitDate)
            ->setTimezone(new DateTimeZone($defaultTimeZone));

        // Convert to human-readable format
        $humanReadableExpirationDate = $dateTime->format('Y-m-d H:i:s T');
        // TODO
        /*
         * make a form for these fields
         * - btnLabel
         * - btnDescription
         * - handle the old PROFILE_LIMIT_DATE
         */
        $form = $this->createForm(SamlProviderExtraOptionsType::class, $samlProvider, [
            'profileLimitDate' => $profileLimitDate,
            'humanReadableExpirationDate' => $humanReadableExpirationDate
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid(),
            ];

            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::ADMIN_EDITED_SAML_PROVIDER_EXTRA_OPTIONS->value,
                new DateTime(),
                $eventMetadata
            );
            $this->addFlash(
                'success_admin',
                'The extra options for the SAML Provider have been successfully updated.'
            );
            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        return $this->render('admin/shared/saml_providers/_saml_provider_form_extra_options.html.twig', [
            'form' => $form->createView(),
            'data' => $data,
            'current_user' => $currentUser,
            'samlProvider' => $samlProvider,
            'profileLimitDate' => $profileLimitDate,
            'humanReadableExpirationDate' => $humanReadableExpirationDate
        ]);
    }

    #[Route(
        '/dashboard/saml-provider/toggle/{id}/{operation}',
        name: 'admin_dashboard_saml_provider_toggle',
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleSamlProvider(
        int $id,
        string $operation, // 'enable' or 'disable'
        Request $request
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $samlProvider = $this->samlProviderRepository->findOneBy(['id' => $id]);
        if (!$samlProvider) {
            $this->addFlash('error_admin', 'This SAML Provider doesn\'t exist!');

            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        // Determine the action (enable or disable)
        $isActive = $operation === 'enable';
        $samlProvider->setActive($isActive)
            ->setUpdatedAt(new DateTime());
        $this->entityManager->persist($samlProvider);
        $this->entityManager->flush();
        // Enable or disable `AUTH_METHOD_SAML_ENABLED` based on active providers.
        $this->authSamlMethodCheckerService->checkAndUpdateAuthMethodStatus();

        // Log the event metadata (tracking the change)
        $eventMetaData = [
            'platform' => PlatformMode::LIVE->value,
            'samlProviderStatus' => $isActive ? 'enabled' : 'disabled',
            'samlProviderName' => $samlProvider->getName(),
            'ip' => $request->getClientIp(),
            'by' => $currentUser->getUuid(),
        ];

        $eventType = $isActive
            ? AnalyticalEventType::ADMIN_ENABLED_SAML_PROVIDER->value
            : AnalyticalEventType::ADMIN_DISABLED_SAML_PROVIDER->value;

        $this->eventActions->saveEvent(
            $currentUser,
            $eventType,
            new DateTime(),
            $eventMetaData
        );

        // Add flash message
        $this->addFlash(
            $isActive ? 'success_admin' : 'error_admin',
            sprintf(
                'SAML Provider "%s" is now %s.',
                $samlProvider->getName(),
                $isActive ? 'enabled' : 'disabled'
            )
        );

        return $this->redirectToRoute('admin_dashboard_saml_provider');
    }

    /**
     * @throws \JsonException
     */
    #[Route('/dashboard/saml-provider/delete/{id}', name: 'admin_dashboard_saml_provider_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteSamlProvider(
        int $id,
        Request $request
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $samlProvider = $this->samlProviderRepository->findOneBy(['id' => $id]);
        if (!$samlProvider) {
            $this->addFlash('error_admin', 'This SAML Provider doesn\'t exist!');

            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        $getSamlProviderName = $samlProvider->getName();
        $userExternalAuth = $this->userExternalAuthRepository->findBy([
            'provider' => UserProvider::SAML->value,
            'samlProvider' => $samlProvider,
        ]);

        foreach ($userExternalAuth as $userExternalAuths) {
            $user = $userExternalAuths->getUser();
            if (!$user) {
                continue; // Skip profile disabling if the user doesn't exist
            }
            if (!$user->getDeletedAt()) {
                // Disable profiles
                $this->profileManager->disableProfiles(
                    $user,
                    UserRadiusProfileRevokeReason::SAML_PROVIDER_DELETED->value
                );
                // Delete associated accounts
                $deleteUserResult = $this->userDeletionService->deleteUser(
                    $user,
                    $userExternalAuth,
                    $request,
                    $currentUser
                );
                // Handle the failure response in case of missing PGP details
                if (!$deleteUserResult['success']) {
                    $this->addFlash('error_admin', $deleteUserResult['message']);

                    return $this->redirectToRoute('admin_page');
                }
            }
        }

        $deleteSamlProviderResult = $this->samlProviderDeletionService->deleteSamlProvider(
            $samlProvider,
            $request,
            $currentUser,
        );

        // Enable or disable `AUTH_METHOD_SAML_ENABLED` based on active providers.
        $this->authSamlMethodCheckerService->checkAndUpdateAuthMethodStatus();
        // Handle the failure response in case of missing PGP details
        if (!$deleteSamlProviderResult['success']) {
            $this->addFlash('error_admin', $deleteSamlProviderResult['message']);

            return $this->redirectToRoute('admin_page');
        }

        $this->addFlash(
            'success_admin',
            sprintf('User with the UUID "%s" deleted successfully.', $getSamlProviderName)
        );

        return $this->redirectToRoute('admin_dashboard_saml_provider');
    }

    /**
     * @throws \JsonException
     */
    #[Route('/dashboard/saml-provider/revoke/{id}', name: 'admin_dashboard_saml_provider_revoke', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function revokeSamlProvider(
        int $id,
        Request $request
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $samlProvider = $this->samlProviderRepository->find($id);
        if (!$samlProvider) {
            $this->addFlash('error_admin', 'This SAML Provider doesn\'t exist!');

            return $this->redirectToRoute('admin_dashboard_saml_provider');
        }

        $getSamlProviderName = $samlProvider->getName();
        $userExternalAuth = $this->userExternalAuthRepository->findBy([
            'provider' => UserProvider::SAML->value,
            'samlProvider' => $samlProvider,
        ]);

        foreach ($userExternalAuth as $userExternalAuths) {
            $user = $userExternalAuths->getUser();

            // Check if the user exists and is not deleted
            if (!$user || $user->getDeletedAt()) {
                continue;
            }

            // Fetch all ACTIVE profiles for the user
            $profiles = $this->profileManager->getActiveProfilesByUser($user);
            foreach ($profiles as $profile) {
                // Check if the profile is already revoked or has REVOKED status
                if ($profile->getStatus() === UserRadiusProfileStatus::REVOKED->value) {
                    continue; // Ignore profiles already marked as REVOKED
                }

                if ($profile->getRevokedReason() === UserRadiusProfileRevokeReason::SAML_PROVIDER_REVOKED->value) {
                    // If the profile has already been revoked for this reason, skip it
                    continue;
                }

                // Disable the profile and set the revoke reason to SAML_PROVIDER_REVOKED
                $this->profileManager->disableProfiles(
                    $user,
                    UserRadiusProfileRevokeReason::SAML_PROVIDER_REVOKED->value,
                    true
                );
            }
        }

        $eventMetadata = [
            'samlProvider' => $samlProvider->getName(),
            'revokedBy' => $currentUser->getUuid(),
            'ip' => $request->getClientIp(),
        ];

        // Log the revoke action of the SAML provider profiles
        $this->eventActions->saveEvent(
            $currentUser,
            AnalyticalEventType::REVOKED_SAML_PROVIDER_BY->value,
            new DateTime(),
            $eventMetadata
        );

        $this->addFlash(
            'success_admin',
            sprintf(
                'All Profiles associated with this SamlProvider "%s" have been revoked successfully.',
                $getSamlProviderName
            )
        );

        return $this->redirectToRoute('admin_dashboard_saml_provider');
    }
}
