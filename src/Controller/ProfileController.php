<?php

namespace App\Controller;


use App\RadiusDb\Entity\RadiusUser;
use App\RadiusDb\Repository\RadiusUserRepository;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig', [
            'controller_name' => 'ProfileController',
        ]);
    }

    #[Route('/profile/android', name: 'profile_android')]
    public function profileAndroid(ManagerRegistry $entityManager, RadiusUserRepository $radiusUserRepository, UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        //check if radius user exists
        $radiususer = $radiusUserRepository->findOneBy(['username' => $user->getUserIdentifier() . "@" . $this->getParameter('app.radius_realm')]);
        if (!$radiususer) {
            $user->setRadiusToken($this->generateToken());
            $userRepository->save($user, true);
            //create radius user
            $radiususer = new RadiusUser();
            $radiususer->setUsername($user->getUserIdentifier() . "@" . $this->getParameter('app.radius_realm'));
            $radiususer->setAttribute('Cleartext-Password');
            $radiususer->setOp(':=');
            $radiususer->setValue($user->getRadiusToken());
            $radiusUserRepository->save($radiususer, true);
        }
//        dd($radiususer);
        $profile = file_get_contents('../profile_templates/profile.xml');
        $profile = str_replace('@USERNAME@', $radiususer->getUsername(), $profile);
        $profile = str_replace('@PASSWORD@', base64_encode($radiususer->getValue()), $profile);
        $profileTemplate = file_get_contents('../profile_templates/template.txt');
        $ca = file_get_contents('../profile_templates/ca.pem');
        $profileTemplate = str_replace('@CA@', $ca, $profileTemplate);
        $profileTemplate = str_replace('@PROFILE@', base64_encode($profile), $profileTemplate);
        $response = new Response(base64_encode($profileTemplate));

        $response->headers->set('Content-Type', 'application/x-wifi-config');
        $response->headers->set('Content-Transfer-Encoding', 'base64');

        return $response;
    }

    private function generateToken($length = 16)
    {
        $stringSpace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $pieces = [];
        $max = mb_strlen($stringSpace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces[] = $stringSpace[random_int(0, $max)];
        }
        return implode('', $pieces);
    }
}
