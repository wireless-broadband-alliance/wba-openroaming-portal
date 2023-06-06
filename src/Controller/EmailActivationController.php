<?php

namespace App\Controller;

use App\Entity\User;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class EmailActivationController extends AbstractController
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer; // to send and build the email
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    public function create_email_code(string $code): void # Send the authentication code via email.
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $email = $currentUser->getEmail();

        $message = (new Email())
            ->from('testing@emails.com')
            ->to($email)
            ->subject('Authentication Code')
            ->text('Your authentication code: ' . $code);

        $this->mailer->send($message); // send the email
    }

    /**
     * @throws Exception
     */
    #[Route('email', name: 'app_email_code')]
    public function sendCode(SessionInterface $session): Response
    {
        // Generate a random code with 6 digits
        $code = random_int(100000, 999999);

        // Store the code in the session
        $session->set('generated_code', $code);

        // Render the template with the code
        return $this->render('email_activation/index.html.twig', ['code' => $code]);
    }

    #[Route('email/check', name: 'app_check_email_code')]
    public function verifyCode(RequestStack $requestStack, SessionInterface $session): Response
    {
        $enteredCode = $requestStack->getCurrentRequest()->request->get('code');

        // Retrieve the generated code from the session
        $generatedCode = $session->get('generated_code');

        // Compare the entered code with the generated code
        $CorrectCode = $enteredCode === $generatedCode;

        if ($CorrectCode) {
            // Code is correct, display success message or perform further actions
            return $this->render('email_activation/success.html.twig', ['correct_code' => true]);
        }
        // Code is incorrect, display error message or redirect
        return $this->render('email_activation/success.html.twig', ['correct_code' => null]);

    }

    /**
     * Check if the entered code matches the code sent via email.
     *
     * @param string $enteredCode
     * @param RequestStack $requestStack
     * @return bool
     */
    private function checkCode(string $enteredCode, RequestStack $requestStack): bool
    {
        // Retrieve the code that was generated and sent via email
        $session = $requestStack->getSession();
        $generatedCode = $session->get('generated_code');

        // Perform the code verification
        return $enteredCode === $generatedCode;
    }
}
