<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class EmailActivationController extends AbstractController
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer; // to send and build the email
    }

    /**
     * @throws Exception
     */
    public function createEmailCode(string $code): Email
    {
        // Get the current user's email
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $email = $currentUser->getEmail();

        // Generate the email with the code
        return (new Email())
            ->from(new Address('openroaming@test_email.pt', 'OpenRoaming Testing Emails'))
            ->to($email)
            ->subject('Authentication Code')
            ->text('Your authentication code: ' . $code);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    #[Route('/email', name: 'app_email_code')]
    public function sendCode(SessionInterface $session, UserRepository $userRepository): Response
    {
        // Get the current user
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Check if the user is already verified
        if ($currentUser->isVerified() === true) {
            // User is already verified, display an error message or redirect
            return $this->render('email_activation/already_verified.html.twig');
        }

        // Generate a random code with 6 digits
        $code = random_int(100000, 999999);

        // Store the code in the session
        $session->set('generated_code', $code);

        // Create the email message
        $message = $this->createEmailCode($code);

        // Send the email
        $this->mailer->send($message);

        // Render the template with the code
        return $this->render('email_activation/index.html.twig', ['code' => $code]);
    }


    #[Route('/email/check', name: 'app_check_email_code')]
    public function verifyCode(RequestStack $requestStack, SessionInterface $session, UserRepository $userRepository): Response
    {
        // Get the entered code from the form
        $enteredCode = $requestStack->getCurrentRequest()->request->get('code');

        // Retrieve the generated code from the session
        $generatedCode = $session->get('generated_code');

        // Compare the entered code with the generated code
        $isCodeCorrect = (int) $enteredCode === $generatedCode;

        if ($isCodeCorrect) {
            // Get the current user
            /** @var User $currentUser */
            $currentUser = $this->getUser();

            // Set the user as verified
            $currentUser->setIsVerified(1);
            $userRepository->save($currentUser, true);

            // Code is correct, display success message or perform further actions
            return $this->render('email_activation/success.html.twig', ['correct_code' => true]);
        }

        // Code is incorrect, display error message or redirect
        return $this->render('email_activation/success.html.twig', ['correct_code' => null]);
    }
}
