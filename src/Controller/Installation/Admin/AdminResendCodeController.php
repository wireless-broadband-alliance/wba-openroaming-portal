<?php

namespace App\Controller\Installation\Admin;

use App\Entity\Event;
use App\Entity\InstallationProgress;
use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\SettingName;
use App\Repository\EventRepository;
use App\Repository\SettingRepository;
use App\Service\EventActions;
use App\Service\InstallationService;
use App\Service\TwoFAService;
use DateTime;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted(AdminRoleType::ROLE_SUPER_ADMIN->value)]
#[Route('/dashboard/settings/certificatesManagement/installation')]
class AdminResendCodeController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly InstallationService $installationService,
        private readonly TwoFAService $twoFAService,
        private readonly SettingRepository $settingRepository,
        private readonly EventRepository $eventRepository,
        private readonly EventActions $eventActions,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     * @throws RandomException
     * @throws TransportExceptionInterface
     */
    #[Route(
        '/admin/confirmation/resend',
        name: 'admin_dashboard_settings_certs_installation_admin_confirmation_resend',
    )]
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $this->addFlash(
                'error',
                $this->translator->trans('onlyAccessThisPageLoggedIn', [], 'controllers')
            );
            return $this->redirectToRoute('app_landing');
        }

        $timeToResetAttempts = (int)$this->settingRepository->findOneBy(
            ['name' => SettingName::TWO_FACTOR_AUTH_TIME_RESET_ATTEMPTS->value]
        )->getValue();
        $nrAttempts = (int)$this->settingRepository->findOneBy(
            ['name' => SettingName::TWO_FACTOR_AUTH_ATTEMPTS_NUMBER_RESEND_CODE->value]
        )->getValue();
        $timeIntervalToResendCode = (int)$this->settingRepository->findOneBy(
            ['name' => SettingName::TWO_FACTOR_AUTH_RESEND_INTERVAL->value]
        )->getValue();
        $limitTime = new DateTime();
        $limitTime->modify('-' . $timeToResetAttempts . ' minutes');

        $eventType = AnalyticalEventType::INSTALLATION_ADMIN_CONFIRM_CODE_RESENT->value;

        if (
            $this->twoFAService->canResendCode($user, $eventType) &&
            $this->twoFAService->timeIntervalToResendCode($user, $eventType)
        ) {
            $lastInstallation = $this->installationService->lastInstallation();
            if ($lastInstallation instanceof InstallationProgress) {
                $this->installationService->sendAdminConfirmationCode($lastInstallation);

                $this->eventActions->saveEvent(
                    $user,
                    AnalyticalEventType::INSTALLATION_ADMIN_CONFIRM_CODE_RESENT->value,
                    new DateTime(),
                    [
                        EventMetadataKeysType::IP->value => $request->getClientIp(),
                        EventMetadataKeysType::USER_AGENT->value => $request->headers->get('User-Agent'),
                        EventMetadataKeysType::UUID->value => $user->getUuid(),
                    ]
                );

                $attempts = $this->eventRepository->find2FACodeAttemptEvent(
                    $user,
                    $nrAttempts,
                    $limitTime,
                    $eventType
                );
                $attemptsLeft = $nrAttempts - count($attempts);

                $this->addFlash(
                    'success',
                    $this->translator->trans(
                        'codeResentSuccessfully',
                        ['%attempts%' => $attemptsLeft],
                        'controllers'
                    )
                );
            }
        } else {
            $lastEvent = $this->eventRepository->findLatest2FACodeAttemptEvent($user, $eventType);
            $now = new DateTime();

            $lastAttemptTime = $lastEvent instanceof Event
                ? $lastEvent->getEventDatetime()
                : new DateTime();

            $limitTime = $lastAttemptTime instanceof DateTime
                ? clone $lastAttemptTime
                : new DateTime($lastAttemptTime->format('Y-m-d H:i:s'));

            if (!$this->twoFAService->canResendCode($user, $eventType)) {
                $limitTime->modify('+' . $timeToResetAttempts . ' minutes');
                $interval = date_diff($now, $limitTime);
                $interval_minutes = $interval->days * 1440;
                $interval_minutes += $interval->h * 60;
                $interval_minutes += $interval->i;

                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'attemptsExceeded',
                        ['%minutes%' => $interval_minutes],
                        'controllers'
                    )
                );
            } else {
                $limitTime->modify('+' . $timeIntervalToResendCode . ' seconds');
                $interval = date_diff($now, $limitTime);
                $interval_seconds = $interval->days * 1440;
                $interval_seconds += $interval->h * 60;
                $interval_seconds += $interval->i;
                $interval_seconds += $interval->s;

                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'errorAdminWait',
                        ['%time%' => $interval_seconds],
                        'controllers'
                    )
                );
            }
        }

        return $this->redirectToRoute('admin_dashboard_settings_certs_installation_admin_confirmation');
    }
}
