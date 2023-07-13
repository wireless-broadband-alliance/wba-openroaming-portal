<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRadiusProfileStatus;
use App\Form\UserUpdateType;
use App\RadiusDb\Entity\RadiusUser;
use App\Repository\SettingRepository;
use App\Repository\UserRadiusProfileRepository;
use App\Repository\UserRepository;
use App\Service\ProfileManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\RadiusDb\Repository\RadiusUserRepository;

class AdminController extends AbstractController
{
    private $userRepository;
    private $settingRepository;
    private $radiusUserRepository;

    private $profileManager;

    public function __construct(
        UserRepository              $userRepository,
        SettingRepository           $settingRepository,
        RadiusUserRepository        $radiusUserRepository,
        UserRadiusProfileRepository $userRadiusProfile,
        ProfileManager              $profileManager
    )
    {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->radiusUserRepository = $radiusUserRepository;
        $this->userRadiusProfile = $userRadiusProfile;
        $this->profileManager = $profileManager;
    }

    #[Route('/dashboard', name: 'admin_page')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request): Response
    {
        $searchTerm = $request->query->get('u');

        return $this->render('admin/index.html.twig', [
            'totalPages' => 0,
            'searchTerm' => $searchTerm,
        ]);
    }

    #[Route('/dashboard/search', name: 'admin_search', methods: ['GET'])]
    public function searchUsers(Request $request, UserRepository $userRepository): Response
    {
        $searchTerm = $request->query->get('u');
        $page = $request->query->getInt('page', 1); // Get the current page from the query parameter
        $perPage = 10; // Number of users to display per page

        // Search users based on the provided search term
        $users = $userRepository->findExcludingAdminWithSearch($searchTerm);

        // Only let the user type more of 3 and less than 320 letters on the search bar
        if (empty($searchTerm) || strlen($searchTerm) < 3) {
            $this->addFlash('error_empty', 'Please enter at least 3 characters for the search.');

            return $this->redirectToRoute('admin_page');
        }
        if (strlen($searchTerm) > 320) {
            $this->addFlash('error', 'Please enter a search term with fewer than 320 characters.');
            return $this->redirectToRoute('admin_page');
        }

        // Perform pagination manually
        $totalUsers = count($users); // Get the total number of users for the search term

        $totalPages = ceil($totalUsers / $perPage); // Calculate the total number of pages

        $offset = ($page - 1) * $perPage; // Calculate the offset for slicing the users

        $users = array_slice($users, $offset, $perPage); // Fetch the users for the current page and search term

        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'searchTerm' => $searchTerm,
        ]);
    }

    #[Route('/dashboard/delete/{id<\d+>}', name: 'admin_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteUsers($id, EntityManagerInterface $em): Response
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        // Disable profiles when deleting a user
        $this->disableProfiles($user);

        $email = $user->getEmail();

        $em->remove($user);
        $em->flush();

        $this->addFlash('success_admin', sprintf('User with the email "%s" deleted successfully.', $email));
        return $this->redirectToRoute('admin_page');
    }

    #[Route('/dashboard/edit/{id<\d+>}', name: 'admin_update')]
    #[IsGranted('ROLE_ADMIN')]
    public function editUsers(User $user, Request $request, UserRepository $userRepository): Response
    {
        $form = $this->createForm(UserUpdateType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();

            if ($form->get('bannedAt')->getData()) {
                $user->setBannedAt(new \DateTime());
                $this->disableProfiles($user);
            } else {
                $user->setBannedAt(null);
                $this->enableProfiles($user);
            }

            $userRepository->save($user, true);
            $email = $user->getEmail();
            $this->addFlash('success_admin', sprintf('User with email "%s" updated successfully.', $email));

            return $this->redirectToRoute('admin_page');
        }

        return $this->render(
            'admin/edit.html.twig',
            [
                'form' => $form->createView(),
                'user' => $user
            ]
        );
    }

    private function disableProfiles($user): void
    {
        $this->profileManager->disableProfiles($user);
    }

    private function enableProfiles($user): void
    {
        $this->profileManager->enableProfiles($user);
    }

}
