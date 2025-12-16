<?php

namespace App\Controller;

use App\DTO\DomainBlacklistDTO;
use App\Entity\DomainBlacklist;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Form\DomainBlacklistType;
use App\Repository\DomainBlacklistRepository;
use App\Service\EventActions;
use App\Service\GetSettings;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class DomainBlacklistController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly GetSettings $getSettings,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventActions $eventActions,
    ) {
    }

    #[Route('/dashboard/settings/blacklist', name: 'admin_dashboard_settings_blacklist')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(Request $request, DomainBlacklistRepository $domainBlacklistRepository): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        /** @var array<string, array{value: string, description: string}> $data */
        $data = $this->getSettings->getSettings();

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $domainBlacklistDB = $domainBlacklistRepository->findAll();

        // Initialize DTO from settings
        $dto = new DomainBlacklistDTO($domainBlacklistDB);

        // Create form bound to DTO
        $form = $this->createForm(DomainBlacklistType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Save updated settings
            $blacklist = $dto->toDomainBlacklist($domainBlacklistDB);

            // Add/Update the other ones
            foreach ($blacklist as $domain) {
                $this->entityManager->persist($domain);
            }

            // Delete the delete ones
            foreach ($domainBlacklistDB as $domainDB) {
                $result =
                    array_find($blacklist, fn(DomainBlacklist $domain) => $domain->getId() === $domainDB->getId());

                if (is_null($result)) {
                    $this->entityManager->remove($domainDB);
                }
            }

            $this->entityManager->flush();

            // Log the event
            $this->eventActions->saveEvent(
                $currentUser,
                AnalyticalEventType::SETTING_DOMAIN_BLACKLIST_REQUEST->value,
                new DateTime(),
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'uuid' => $currentUser->getUuid(),
                ]
            );

            $this->addFlash(
                'success_admin',
                $this->translator->trans('domainBlacklistConfigurationAppliedSuccessfully', [], 'controllers')
            );
            return $this->redirectToRoute('admin_dashboard_settings_blacklist');
        }

        return $this->render('dashboard/shared/settings_actions.html.twig', [
            'form' => $form->createView(),
            'formDTO' => $dto,
            'data' => $data,
        ]);
    }
}
