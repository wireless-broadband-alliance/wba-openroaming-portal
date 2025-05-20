<?php

namespace App\Controller;

use App\Entity\Setting;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\LanguagesType;
use App\Form\CustomType;
use App\Form\RevokeProfilesType;
use App\Repository\EventRepository;
use App\Repository\SettingTranslationRepository;
use App\Repository\UserRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\VerificationCodeEmailGenerator;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminController extends AbstractController
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly ParameterBagInterface $parameterBag,
        private readonly GetSettings $getSettings,
        private readonly EventActions $eventActions,
        private readonly VerificationCodeEmailGenerator $verificationCodeGenerator,
        private readonly EventRepository $eventRepository,
        private readonly TranslatorInterface $translator,
        private readonly SettingTranslationRepository $settingTranslationRepository
    ) {
    }

    /**
     * Dashboard Page Main Route
     */
    #[Route('/dashboard', name: 'admin_page')]
    #[IsGranted('ROLE_ADMIN')]
    public function dashboard(
        Request $request,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] string $sort = 'createdAt',
        #[MapQueryParameter] string $order = 'desc',
        #[MapQueryParameter] ?int $count = 7
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings();

        $searchTerm = $request->query->get('u');

        $filter = $request->query->get('filter', 'all'); // Default filter

        // Use the updated searchWithFilter method to handle both filter and search term
        $users = $this->userRepository->searchWithFilter($filter, $sort, $order, $searchTerm);

        // Perform pagination manually
        $totalUsers = count($users);

        $totalPages = ceil($totalUsers / $count);

        $offset = ($page - 1) * $count;

        $users = array_slice($users, $offset, $count);

        // Fetch user counts for table header (All/Verified/Banned)
        $allUsersCount = $this->userRepository->countAllUsersExcludingAdmin($searchTerm, $filter);
        $verifiedUsersCount = $this->userRepository->countVerifiedUsers($searchTerm);
        $bannedUsersCount = $this->userRepository->totalBannedUsers($searchTerm);

        // Check if the export users operation is enabled
        $exportUsers = $this->parameterBag->get('app.export_users');
        // Check if the delete action has a public PGP key defined
        $deleteUsers = $this->parameterBag->get('app.pgp_public_key');
        // Create form views
        $formRevokeProfiles = $this->createForm(RevokeProfilesType::class, $this->getUser());

        /** @var User $user */
        $user = $this->getUser();
        return $this->render('dashboard/dashboard.html.twig', [
            'user' => $user,
            'users' => $users,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'searchTerm' => $searchTerm,
            'data' => $data,
            'allUsersCount' => $allUsersCount,
            'verifiedUsersCount' => $verifiedUsersCount,
            'bannedUsersCount' => $bannedUsersCount,
            'activeFilter' => $filter,
            'activeSort' => $sort,
            'activeOrder' => $order,
            'count' => $count,
            'export_users' => $exportUsers,
            'delete_users' => $deleteUsers,
            'ApUsage' => null,
            'formRevokeProfiles' => $formRevokeProfiles
        ]);
    }

    /**
     * Regenerate the verification code for the user and send a new email.
     *
     * @param string $type Type of action
     * @return RedirectResponse A redirect response.
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    #[Route('/dashboard/regenerate/{type}', name: 'app_dashboard_regenerate_code_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function regenerateCode(string $type, Request $request): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        // Regenerate the verification code for the admin to reset settings

        if (
            in_array(
                $type,
                [
                    'settingCustom',
                    'settingTerms',
                    'settingRadius',
                    'settingStatus',
                    'settingLDAP',
                    'settingCAPPORT',
                    'settingAUTH',
                    'settingTwoFA',
                    'settingSMS'
                ]
            )
        ) {
            $lastResend = $this->eventRepository->findLatest2FACodeAttemptEvent(
                $currentUser,
                AnalyticalEventType::SETTING_RESET_CODE_REQUEST->value
            );
            $timeIntervalInSeconds = 120;
            if ($this->verificationCodeGenerator->canResendCode($currentUser, $timeIntervalInSeconds)) {
                $email = $this->verificationCodeGenerator->createEmailAdminPage(
                    $currentUser,
                    $request->getClientIp(),
                    $request->headers->get('User-Agent')
                );
                $this->mailer->send($email);
                $this->addFlash(
                    'success_admin',
                    $this->translator->trans(
                        'successResendAdmin',
                        ['%email%' => $currentUser->getEmail()],
                        'controllers'
                    )
                );
                return $this->redirectToRoute('admin_confirm_reset', ['type' => $type]);
            }
            $timeLeft = $this->verificationCodeGenerator->timeLeftToResendCode($timeIntervalInSeconds, $lastResend);
            $this->addFlash(
                'error_admin',
                $this->translator->trans(
                    'errorAdminWait',
                    ['%time%' => $timeLeft],
                    'controllers'
                )
            );
            return $this->redirectToRoute('admin_confirm_reset', ['type' => $type]);
        }

        return $this->redirectToRoute('admin_page');
    }

    /**
     * Handles the Page Style on the dashboard
     */
    #[Route(
        '/dashboard/customize/{language}',
        name: 'admin_dashboard_customize',
        defaults: ['language' => LanguagesType::EN->value]
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function customize(Request $request, EntityManagerInterface $em, string $language): Response
    {

        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($language);
        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        // Get the settings value according to the language
        $settingsTranslated = $this->getSettings->getSettingsByLocale($settings, $data);

        // Create the form with the CustomType and pass the relevant settings
        $form = $this->createForm(CustomType::class, null, [
            'settings' => $settingsTranslated,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle submitted data and update the settings accordingly
            $submittedData = $form->getData();

            // Update the settings based on the form submission
            foreach ($settings as $setting) {
                $settingName = $setting->getName();

                // Check if the setting is in the allowed settings for customization
                if (
                    in_array(
                        $settingName,
                        [
                            'WELCOME_TEXT',
                            'PAGE_TITLE',
                            'WELCOME_DESCRIPTION',
                            'ADDITIONAL_LABEL',
                            'CONTACT_EMAIL',
                            'CUSTOMER_LOGO_ENABLED'
                        ]
                    )
                ) {
                    if (in_array($settingName, $this->getSettings->arraySettingsToTranslate(), true)) {
                        $locale = $language;
                        $submittedValue = $submittedData[$settingName];
                        if ($locale === LanguagesType::EN->value) {
                            // Update the setting value
                            $setting->setValue($submittedValue);
                        }
                        // Get the translated setting
                        $settingTranslation = $this->settingTranslationRepository->findOneBy(
                            ['setting' => $setting, 'locale' => $locale]
                        );
                        if($settingName === 'ADDITIONAL_LABEL' && $submittedValue === null){
                            $settingTranslation?->setTranslation('');
                        } else {
                            $settingTranslation?->setTranslation($submittedValue);
                        }
                    } else {
                        // Get the value from the submitted form data
                        $submittedValue = $submittedData[$settingName];

                        // Update the setting value
                        $setting->setValue($submittedValue);
                    }
                } elseif (in_array($settingName, ['CUSTOMER_LOGO', 'OPENROAMING_LOGO', 'WALLPAPER_IMAGE'])) {
                    // Handle file uploads for logos and wallpaper image
                    $file = $form->get($settingName)->getData();

                    if ($file) { // submits the new file to the respective path
                        $originalFilename = pathinfo((string)$file->getClientOriginalName(), PATHINFO_FILENAME);
                        // Use a unique id for the uploaded file to avoid overwriting
                        $newFilename = $originalFilename .
                            '-' .
                            uniqid('', true) .
                            '.' .
                            $file->guessExtension();

                        // Set the destination directory based on the setting name
                        $destinationDirectory = $this->getParameter('kernel.project_dir')
                            . '/public/resources/uploaded/';

                        $file->move($destinationDirectory, $newFilename);
                        $setting->setValue('/resources/uploaded/' . $newFilename);
                    }
                    // PLS MAKE SURE TO USE THIS COMMAND ON THE WEB CONTAINER
                    // chown -R www-data:www-data /var/www/openroaming/public/resources/uploaded/
                }
            }

            $this->addFlash(
                'success_admin',
                $this->translator->trans(
                    'settingsUpdatedSuccessfully',
                    [],
                    'controllers'
                )
            );

            $eventMetadata = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'uuid' => $currentUser->getUuid(),
            ];
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_PAGE_STYLE_REQUEST->value,
                new DateTime(),
                $eventMetadata
            );

            return $this->redirectToRoute('admin_dashboard_customize', ['language' => $language]);
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'user' => $currentUser,
            'settings' => $settingsTranslated,
            'form' => $form->createView(),
            'data' => $data,
            'language' => $language
        ]);
    }
}
