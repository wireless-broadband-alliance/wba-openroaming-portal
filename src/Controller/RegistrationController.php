<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Service\GetSettings;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;

class RegistrationController extends AbstractController
{
    private UserRepository $userRepository;
    private SettingRepository $settingRepository;
    private GetSettings $getSettings;

    private ParameterBagInterface $parameterBag;

    /**
     * SiteController constructor.
     *
     * @param UserRepository $userRepository The repository for accessing user data.
     * @param SettingRepository $settingRepository The setting repository is used to create the getSettings function.
     * @param GetSettings $getSettings The instance of GetSettings class.
     */
    public function __construct(UserRepository $userRepository, SettingRepository $settingRepository, GetSettings $getSettings, ParameterBagInterface $parameterBag)
    {
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->getSettings = $getSettings;
        $this->parameterBag = $parameterBag;
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

        $Email = $this->parameterBag->get('app.email_address');
        $Name = $this->parameterBag->get('app.sender_name');

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
                $user->setCreatedAt(new DateTime());

                $entityManager->persist($user);
                $entityManager->flush();

                // Send email to the user with the encrypted password
                $email = (new TemplatedEmail())
                    ->from(new Address($Email, $Name))
                    ->to($user->getEmail())
                    ->subject('Your OpenRoaming Registration Details')
                    ->htmlTemplate('email_activation/email_template_password.html.twig')
                    ->context([
                        'isNewUser' => true, // This variable lets the template know if the user it's new our if it's just a password reset request
                        'password' => $randomPassword,
                    ]);

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
