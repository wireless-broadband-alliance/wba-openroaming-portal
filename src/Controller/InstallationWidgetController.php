<?php

namespace App\Controller;

use App\DTO\AdminConfigDTO;
use App\DTO\DatabaseConfigDTO;
use App\Entity\InstallationWidget;
use App\Enum\InstallationWidgetStepsEnum;
use App\Form\DatabaseConfigType;
use App\Form\AdminConfigType;
use App\Repository\InstallationWidgetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InstallationWidgetController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InstallationWidgetRepository $installationWidgetRepository,
    ) {
    }

    #[Route('/installation', name: 'app_installation_widget')]
    public function index(EntityManagerInterface $em): Response
    {
        // Always ensure we have a single installation record
        $installation = $em->getRepository(InstallationWidget::class)->findOneBy([]);
        if (!$installation) {
            $installation = new InstallationWidget();
            $em->persist($installation);
            $em->flush();
        }

        return match ($installation->getCurrentStep()) {
            InstallationWidgetStepsEnum::DATABASE => $this->redirectToRoute('app_installation_database'),
            InstallationWidgetStepsEnum::ADMIN_CONFIGURATION => $this->redirectToRoute('app_installation_admin'),
            InstallationWidgetStepsEnum::RECAP => $this->redirectToRoute('app_installation_recap'),
            default => $this->render('installation_widget/done.html.twig'),
        };
    }

    #[Route('/installation/database', name: 'app_installation_database')]
    public function database(Request $request): Response
    {
        $installation = $this->installationWidgetRepository->findOneBy([]);

        $databaseConfigDTO = new DatabaseConfigDTO();

        //TODO: Match each field of the dto with the data in the installation widget

        $form = $this->createForm(
            DatabaseConfigType::class,
            $databaseConfigDTO
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $installation
                ->setStepData(InstallationWidgetStepsEnum::DATABASE, $data)
                ->setStepTimestamp(InstallationWidgetStepsEnum::DATABASE)
                ->setCurrentStep(InstallationWidgetStepsEnum::ADMIN_CONFIGURATION);

            $this->entityManager->flush();

            return $this->redirectToRoute('app_installation_widget');
        }

        return $this->render('installation_widget/database/database.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/installation/admin', name: 'app_installation_admin')]
    public function admin(Request $request): Response
    {
        $installation = $this->installationWidgetRepository->findOneBy([]);

        $adminConfigDTO = new AdminConfigDTO();

        //TODO: Match each field of the dto with the data in the installation widget

        $form = $this->createForm(
            AdminConfigType::class,
            $adminConfigDTO
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $installation
                ->setStepData(InstallationWidgetStepsEnum::ADMIN_CONFIGURATION, $data)
                ->setStepTimestamp(InstallationWidgetStepsEnum::ADMIN_CONFIGURATION)
                ->setCurrentStep(InstallationWidgetStepsEnum::RECAP);

            $this->entityManager->flush();

            return $this->redirectToRoute('app_installation_widget');
        }

        return $this->render('installation_widget/admin/admin.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/installation/recap', name: 'app_installation_recap')]
    public function recap(Request $request): Response
    {
        $installation = $this->installationWidgetRepository->findOneBy([]);

        $allData = $installation->getAllStepData();

        // Handle final confirmation submit
        if ($request->isMethod('POST')) {
            $installation->setCurrentStep(InstallationWidgetStepsEnum::DONE);
            $installation->setStepTimestamp(InstallationWidgetStepsEnum::DONE);

            // At this point you could also "apply" data into the tables/configuration files,
            $this->entityManager->flush();

            return $this->redirectToRoute('app_installation_widget');
        }

        return $this->render('installation_widget/recap.html.twig', [
            'data' => $allData,
        ]);
    }
}
