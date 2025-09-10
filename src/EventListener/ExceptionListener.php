<?php

namespace App\EventListener;

use App\Entity\Setting;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
readonly class ExceptionListener
{
    public function __construct(
        private Environment $twig,
        private TranslatorInterface $translator,
        private SettingRepository $settingRepository,
        private LocaleAwareInterface $translatorLocale,
    ) {
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Default values
        $statusCode = 500;
        $statusTitle = $this->translator->trans(
            'internalServerError',
            [],
            'eventListener'
        );

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $statusTitle = Response::$statusTexts[$statusCode] ?? 'Error';
        }

        // Custom status codes to handle
        $handleCodes = [400, 404, 422, 424, 500, 501, 503];
        if (!in_array($statusCode, $handleCodes, true)) {
            return;
        }

        $data = $this->getSettings();

        // Try specific error template first
        $template = sprintf('bundles/TwigBundle/Exception/error_%d.html.twig', $statusCode);
        if (!$this->twig->getLoader()->exists($template)) {
            $template = 'bundles/TwigBundle/Exception/error.html.twig';
        }

        $request = $event->getRequest();
        $locale = $request->getSession()->get('_locale', 'en');
        $request->setLocale($locale);
        $this->translatorLocale->setLocale($locale);

        $content = $this->twig->render($template, [
            'status_code' => $statusCode,
            'status_title' => $statusTitle,
            'exception' => $exception,
            'data' => $data,
        ]);

        $response = new Response($content, $statusCode);
        $event->setResponse($response);
    }

    private function getSettings(): array
    {
        $wanted = [
            SettingName::PAGE_TITLE->value,
            SettingName::CUSTOMER_LOGO_ENABLED->value,
            SettingName::CUSTOMER_LOGO->value,
            SettingName::WALLPAPER_IMAGE->value
        ];

        $settings = $this->settingRepository->findBy([
            'name' => $wanted,
        ]);

        $result = [];
        foreach ($settings as $setting) {
            /** @var Setting $setting */
            $result[$setting->getName()] = [
                'value' => $setting->getValue(),
            ];
        }

        return $result;
    }
}
