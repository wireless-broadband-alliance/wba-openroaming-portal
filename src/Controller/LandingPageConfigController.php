<?php

namespace App\Controller;

use App\DTO\CustomTypeDTO;
use App\Entity\Setting;
use App\Entity\SettingTranslation;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\FirewallType;
use App\Enum\LanguageType;
use App\Enum\OSType;
use App\Enum\SettingName;
use App\Form\AccountUserUpdateLandingType;
use App\Form\CustomType;
use App\Form\NewPasswordAccountType;
use App\Form\RegistrationFormType;
use App\Form\RevokeProfilesType;
use App\Form\TOSType;
use App\Repository\SettingTranslationRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\EventActions;
use App\Service\GetSettings;
use App\Service\HtmlSanitizerService;
use App\Service\OSDetectionService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LandingPageConfigController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
        private readonly EventActions $eventActions,
        private readonly TranslatorInterface $translator,
        private readonly SettingTranslationRepository $settingTranslationRepository,
        private readonly HtmlSanitizerService $htmlSanitizerService,
    ) {
    }

    /**
     * Handles the Page Style on the dashboard
     */
    #[Route(
        '/dashboard/customize/{language}',
        name: 'admin_dashboard_customize',
        defaults: ['language' => LanguageType::EN->value]
    )]
    #[IsGranted(UserAuthenticationVoter::LANDING_PAGE_CONFIG_READ)]
    public function customize(Request $request, EntityManagerInterface $em, string $language): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings($language);

        // Get the current logged-in user (admin)
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $canWrite = $this->isGranted(UserAuthenticationVoter::LANDING_PAGE_CONFIG_WRITE);

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();

        // Get the settings value according to the language
        $settingsTranslated = $this->getSettings->getSettingsByLocale($settings, $data);

        $customTypeDTO = new CustomTypeDTO();

        // Create the form with the CustomType and pass the relevant settings
        $form = $this->createForm(CustomType::class, $customTypeDTO, [
            'settings' => $settingsTranslated,
            'disabled' => !$canWrite,
        ]);

        $form->handleRequest($request);
        if ($canWrite && $form->isSubmitted() && $form->isValid()) {
            // Update the settings based on the form submission
            $changeset = [];
            foreach ($settings as $setting) {
                $settingName = $setting->getName();

                // Check if the setting is in the allowed settings for customization
                if (
                    in_array($settingName, [
                        SettingName::WELCOME_TEXT->value,
                        SettingName::PAGE_TITLE->value,
                        SettingName::WELCOME_DESCRIPTION->value,
                        SettingName::ADDITIONAL_LABEL->value,
                        SettingName::CONTACT_EMAIL->value,
                        SettingName::CUSTOMER_LOGO_ENABLED->value,
                        SettingName::FOOTER_IMAGE_ENABLED->value,
                    ], true)
                ) {
                    if (in_array($settingName, $this->getSettings->arraySettingsToTranslate(), true)) {
                        $locale = $language;
                        $submittedValue = $customTypeDTO->{$settingName} ?? null;
                        $sanitizedValue = $this->htmlSanitizerService->sanitize($submittedValue);
                        if ($locale === LanguageType::EN->value) {
                            // Update the setting value
                            if ($data[$settingName]['value'] !== $sanitizedValue) {
                                $changeset[$settingName] = [
                                    'oldValue' => $data[$settingName]['value'],
                                    'newValue' => $sanitizedValue,
                                ];
                            }
                            $setting->setValue($sanitizedValue);
                        }
                        // Get the translated setting
                        $settingTranslation = $this->settingTranslationRepository->findOneBy(
                            ['setting' => $setting, 'locale' => $locale]
                        );

                        if (!$settingTranslation instanceof SettingTranslation) {
                            $settingTranslation = new SettingTranslation();
                            $settingTranslation->setSetting($setting);
                            $settingTranslation->setLocale($locale);
                            $em->persist($settingTranslation);
                        }

                        if ($settingName === SettingName::ADDITIONAL_LABEL->value && $submittedValue === null) {
                            $changeset[$settingName] = [
                                'oldValue' => $data[$settingName]['value'],
                                'newValue' => '',
                            ];
                            $settingTranslation->setTranslation('');
                        } else {
                            if ($data[$settingName]['value'] !== $sanitizedValue) {
                                $changeset[$settingName] = [
                                    'oldValue' => $data[$settingName]['value'],
                                    'newValue' => $sanitizedValue,
                                ];
                            }
                            $settingTranslation->setTranslation($sanitizedValue);
                        }
                    } else {
                        // Get the value from the submitted form data
                        $submittedValue = $customTypeDTO->{$settingName} ?? null;
                        if ($data[$settingName]['value'] !== $submittedValue) {
                            $changeset[$settingName] = [
                                'oldValue' => $data[$settingName]['value'],
                                'newValue' => $submittedValue,
                            ];
                        }
                        // Update the setting value
                        $setting->setValue($submittedValue);
                    }
                } elseif (
                    in_array(
                        $settingName,
                        [
                            SettingName::CUSTOMER_LOGO->value,
                            SettingName::OPENROAMING_LOGO->value,
                            SettingName::WALLPAPER_IMAGE->value,
                            SettingName::FOOTER_IMAGE->value,
                        ],
                        true
                    )
                ) {
                    $file = $form->get($settingName)->getData();
                    $removeRequested = $form->has($settingName . '_REMOVE')
                        && $form->get($settingName . '_REMOVE')->getData();

                    if ($file) { // submits the new file to the respective path
                        $originalFilename = pathinfo((string)$file->getClientOriginalName(), PATHINFO_FILENAME);
                        $newFilename = $originalFilename . '-' . uniqid('', true) . '.' . $file->guessExtension();

                        // Set the destination directory based on the setting name
                        $destinationDirectory = $this->getParameter('kernel.project_dir')
                            . '/public/resources/uploaded/';

                        $file->move($destinationDirectory, $newFilename);

                        if ($data[$settingName]['value'] !== '/resources/uploaded/' . $newFilename) {
                            $changeset[$settingName] = [
                                'oldValue' => $data[$settingName]['value'],
                                'newValue' => '/resources/uploaded/' . $newFilename,
                            ];
                        }
                        $setting->setValue('/resources/uploaded/' . $newFilename);
                    } elseif ($removeRequested) {
                        $currentValue = $data[$settingName]['value'];

                        if ($currentValue) {
                            $uploadedDirectory = $this->getParameter(
                                    'kernel.project_dir'
                                ) . '/public/resources/uploaded';
                            $realUploadedDirectory = realpath($uploadedDirectory);

                            $absolutePath = $this->getParameter(
                                    'kernel.project_dir'
                                ) . '/public' . $currentValue;
                            $realAbsolutePath = realpath($absolutePath);

                            if (
                                $realUploadedDirectory !== false
                                && $realAbsolutePath !== false
                                && str_starts_with($realAbsolutePath, $realUploadedDirectory . DIRECTORY_SEPARATOR)
                            ) {
                                @unlink($realAbsolutePath);
                            }
                        }

                        if ($currentValue !== '') {
                            $changeset[$settingName] = [
                                'oldValue' => $currentValue,
                                'newValue' => '',
                            ];
                        }

                        $setting->setValue('');
                    }
                }
            }

            $em->flush();

            $this->addFlash(
                'success',
                $this->translator->trans(
                    'settingsUpdatedSuccessfully',
                    [],
                    'controllers'
                )
            );

            $eventMetadata = [
                EventMetadataKeysType::IP->value => $request->getClientIp(),
                EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                EventMetadataKeysType::UUID->value => $currentUser->getUuid(),
                EventMetadataKeysType::CHANGESET->value => $changeset,
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
            'language' => $language,
            'customTypeDTO' => $customTypeDTO,
        ]);
    }

    /**
     * Handles the Preview of the Page Style
     */
    #[Route(
        '/dashboard/customize/{language}/preview',
        name: 'admin_dashboard_customize_preview',
        defaults: ['language' => LanguageType::EN->value]
    )]
    #[IsGranted(UserAuthenticationVoter::LANDING_PAGE_CONFIG_WRITE)]
    public function previewCustomization(
        Request $request,
        EntityManagerInterface $em,
        string $language,
        OSDetectionService $OSDetectionService,
        TokenStorageInterface $tokenStorage,
    ): Response {
        $request->attributes->set('_locale', $language);

        $request->setLocale($language);

        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $language);
        }

        if ($this->translator instanceof LocaleAwareInterface) {
            $this->translator->setLocale($language);
        }

        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings($language);

        $settingsRepository = $em->getRepository(Setting::class);
        $settings = $settingsRepository->findAll();
        $settingsTranslated = $this->getSettings->getSettingsByLocale($settings, $data);

        $customTypeDTO = new CustomTypeDTO();

        $form = $this->createForm(CustomType::class, $customTypeDTO, [
            'settings' => $settingsTranslated,
            'disabled' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $textSettings = [
                SettingName::WELCOME_TEXT->value,
                SettingName::PAGE_TITLE->value,
                SettingName::WELCOME_DESCRIPTION->value,
                SettingName::ADDITIONAL_LABEL->value,
                SettingName::CONTACT_EMAIL->value,
                SettingName::CUSTOMER_LOGO_ENABLED->value,
                SettingName::FOOTER_IMAGE_ENABLED->value,
            ];

            foreach ($textSettings as $settingName) {
                $submittedValue = $customTypeDTO->{$settingName} ?? null;

                if (in_array($settingName, $this->getSettings->arraySettingsToTranslate(), true)) {
                    $submittedValue = $this->htmlSanitizerService->sanitize($submittedValue ?? '');
                }

                if ($submittedValue !== null) {
                    $data[$settingName]['value'] = $submittedValue;
                } elseif ($settingName === SettingName::ADDITIONAL_LABEL->value) {
                    $data[$settingName]['value'] = '';
                }
            }

            $imageSettings = [
                SettingName::CUSTOMER_LOGO->value,
                SettingName::OPENROAMING_LOGO->value,
                SettingName::WALLPAPER_IMAGE->value,
                SettingName::FOOTER_IMAGE->value,
            ];

            foreach ($imageSettings as $settingName) {
                $file = $form->get($settingName)->getData();

                if ($file) {
                    $mimeType = $file->getMimeType();
                    $fileContents = file_get_contents($file->getPathname());

                    if ($fileContents === false) {
                        continue;
                    }

                    $base64 = base64_encode($fileContents);
                    $data[$settingName]['value'] = 'data:' . $mimeType . ';base64,' . $base64;
                }
            }
        }

        $userAgent = $request->headers->get('User-Agent');
        $data['os'] = [
            'selected' => $OSDetectionService->detectDevice($userAgent),
            'items' => [
                OSType::WINDOWS->value => ['alt' => 'Windows Logo'],
                OSType::IOS->value => ['alt' => 'Apple Logo'],
                OSType::ANDROID->value => ['alt' => 'Android Logo']
            ]
        ];

        $dummyUser = new User();

        $landingForm = $this->createForm(AccountUserUpdateLandingType::class, $dummyUser);
        $formPassword = $this->createForm(NewPasswordAccountType::class, $dummyUser);
        $formRevokeProfiles = $this->createForm(RevokeProfilesType::class, $dummyUser);
        $formRegistrationDemo = $this->createForm(RegistrationFormType::class, $dummyUser);
        $formTOS = $this->createForm(TOSType::class);

        $originalToken = $tokenStorage->getToken();
        $tokenStorage->setToken(null);

        try {
            $response = $this->render('landing/landing.html.twig', [
                'form' => $landingForm->createView(),
                'formPassword' => $formPassword->createView(),
                'formTOS' => $formTOS,
                'formRevokeProfiles' => $formRevokeProfiles->createView(),
                'registrationFormDemo' => $formRegistrationDemo->createView(),
                'data' => $data,
                'userExternalAuths' => [],
                'user' => null,
                'context' => FirewallType::LANDING->value,
                'isPreview' => true,
            ]);
        } finally {
            $tokenStorage->setToken($originalToken);
        }

        return $response;
    }
}
