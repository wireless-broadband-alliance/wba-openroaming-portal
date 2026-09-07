<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\AdminRoleType;
use App\Enum\AnalyticalEventType;
use App\Enum\SettingType;
use App\Form\RevokeProfilesType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Security\Voter\UserAuthenticationVoter;
use App\Service\GetSettings;
use App\Service\VerificationCodeEmailGenerator;
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
        private readonly VerificationCodeEmailGenerator $verificationCodeGenerator,
        private readonly EventRepository $eventRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Dashboard Page Main Route
     */
    #[Route('/dashboard', name: 'admin_page')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function dashboard(): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$this->isGranted(UserAuthenticationVoter::USERS_MANAGEMENT_READ)) {
            return $this->redirectToRoute('admin_dashboard_user_edit', ['id' => $currentUser->getId()]);
        }

        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        $exportUsers = $this->parameterBag->get('app.export_users');
        $deleteUsers = $this->parameterBag->get('app.pgp_public_key');
        $formRevokeProfiles = $this->createForm(RevokeProfilesType::class, $this->getUser());

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/dashboard.html.twig', [
            'user' => $user,
            'data' => $data,
            'export_users' => $exportUsers,
            'delete_users' => $deleteUsers,
            'formRevokeProfiles' => $formRevokeProfiles,
        ]);
    }

    #[Route('/dashboard/admins', name: 'admin_dashboard_admins')]
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function adminRolesManagement(
        Request $request,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] string $sort = 'createdAt',
        #[MapQueryParameter] string $order = 'desc',
        #[MapQueryParameter] ?int $count = 7
    ): Response {
        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        $searchTerm = $request->query->get('u');

        $filter = $request->query->get('filter', 'all'); // Default filter

        // Use the updated searchWithFilter method to handle both filter and search term
        $users = $this->userRepository->searchAdminUsers($filter, $sort, $order, $searchTerm);

        // Perform pagination manually
        $totalUsers = count($users);

        $totalPages = ceil($totalUsers / $count);

        $offset = ($page - 1) * $count;

        $users = array_slice($users, $offset, $count);

        // Fetch user counts for table header (All/Verified/Banned)
        $allUsersCount = $this->userRepository->countUsers($searchTerm, $filter, true);
        $verifiedUsersCount = $this->userRepository->countVerifiedUsers($searchTerm, true);
        $bannedUsersCount = $this->userRepository->countBannedUsers($searchTerm, true);

        // Check if the delete action has a public PGP key defined
        $deleteUsers = $this->parameterBag->get('app.pgp_public_key');
        // Create form views
        $formRevokeProfiles = $this->createForm(RevokeProfilesType::class, $this->getUser());

        /** @var User $user */
        $user = $this->getUser();
        return $this->render('dashboard/dashboard_admins.html.twig', [
            'currentUser' => $user,
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
    #[IsGranted(AdminRoleType::ROLE_ADMIN->value)]
    public function regenerateCode(string $type, Request $request): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Regenerate the verification code for the admin to reset settings
        if (
            in_array($type, [
                SettingType::SettingCustom->value,
                SettingType::SettingTerms->value,
                SettingType::SettingRadius->value,
                SettingType::SettingStatus->value,
                SettingType::SettingLDAP->value,
                SettingType::SettingCAPPORT->value,
                SettingType::SettingAUTH->value,
                SettingType::SettingTwoFA->value,
                SettingType::SettingSMS->value,
                SettingType::SettingSchedule->value,
                SettingType::SettingsReturnApps->value,
            ], true)
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
                    $request->headers->get('User-Agent'),
                    $type
                );

                $this->mailer->send($email);
                $this->addFlash(
                    'success',
                    $this->translator->trans(
                        'successResendAdmin',
                        ['%email%' => $currentUser->getEmail()],
                        'controllers'
                    )
                );

                return $this->redirectToRoute('admin_dashboard_confirm_reset', ['type' => $type]);
            }

            $timeLeft = $this->verificationCodeGenerator->timeLeftToResendCode($timeIntervalInSeconds, $lastResend);

            $this->addFlash(
                'error',
                $this->translator->trans(
                    'errorAdminWait',
                    ['%time%' => $timeLeft],
                    'controllers'
                )
            );

            return $this->redirectToRoute('admin_dashboard_confirm_reset', ['type' => $type]);
        }

        return $this->redirectToRoute('admin_page');
    }
}
