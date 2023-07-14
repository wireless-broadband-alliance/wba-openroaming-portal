<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\Mailer\MailerInterface;

class RegistrationController extends AbstractController
{
    private UserRepository $userRepository;
    private SettingRepository $settingRepository;
    private GetSettings $getSettings;

    /**
     * SiteController constructor.
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     */
    public function __construct(UserRepository $userRepository, SettingRepository $settingRepository, GetSettings $getSettings)
    {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, RequestStack $requestStack, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        // Call the getSettings method of GetSettings class to retrieve the data
        $data = $this->getSettings->getSettings($this->userRepository, $this->settingRepository, $request, $requestStack);

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($this->userRepository->findOneBy(['email' => $user->getEmail()])) {
                $this->addFlash('warning', 'User with the same email already exists.');
            } else {
                // Generate a random password
                $randomPassword = bin2hex(random_bytes(8));

                // Hash the password
                $hashedPassword = $userPasswordHasher->hashPassword($user, $randomPassword);

                // Set the hashed password for the user
                $user->setPassword($hashedPassword);
                $user->setUuid($user->getEmail());
                $user->isVerified(0);
                $user->setCreatedAt(new \DateTime());

                $entityManager->persist($user);
                $entityManager->flush();

                // Send email to the user with the encrypted password
                $email = (new Email())
                    ->from('openroaming@tetrapi.pt')
                    ->to($user->getEmail())
                    ->subject('Your Registration Details')
                    ->html(
                        $this->renderView(
                            'email_activation/email_template_password.html.twig',
                            ['password' => $randomPassword]
                        )
                    );

                $this->addFlash('success', 'We have sent an email with your account password');
                $mailer->send($email);
            }
        }

        return $this->render('site/register_landing.html.twig', [
            'registrationForm' => $form->createView(),
            'data' => $data,
        ]);
    }


}
