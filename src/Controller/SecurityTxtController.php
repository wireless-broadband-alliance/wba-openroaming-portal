<?php

namespace App\Controller;

use App\Enum\SettingName;
use App\Service\GetSettings;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SecurityTxtController extends AbstractController
{
    public function __construct(
        private readonly GetSettings $getSettings,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     */
    #[Route('/.well-known/security.txt', name: 'app_security_txt', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $settings = $this->getSettings->getSpecificSettings([
            SettingName::SECURITY_CONTACT->value,
            SettingName::SECURITY_EXPIRES->value,
            SettingName::SECURITY_PGP_FINGERPRINT->value,
        ]);

        $contact = $this->getSettingValue(
            $settings,
            SettingName::SECURITY_CONTACT
        );

        $expires = $this->getSettingValue(
            $settings,
            SettingName::SECURITY_EXPIRES
        );

        $fingerprint = $this->getSettingValue(
            $settings,
            SettingName::SECURITY_PGP_FINGERPRINT
        );

        // Contact and Expires are mandatory in RFC 9116:
        // don't serve a broken security.txt file.
        if ($contact === null || $expires === null) {
            throw $this->createNotFoundException();
        }

        if (!preg_match('#^(mailto:|https://|tel:)#i', $contact)) {
            $contact = 'mailto:' . $contact;
        }

        $expiresUtc = new DateTimeImmutable($expires)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.000\Z');

        $lines = [
            'Contact: ' . $contact,
            'Expires: ' . $expiresUtc,
        ];

        if ($fingerprint !== null) {
            $lines[] = 'Encryption: openpgp4fpr:' . strtoupper(
                    preg_replace('/\s+/', '', $fingerprint)
                );
        }

        $lines[] = 'Canonical: ' . $request->getSchemeAndHttpHost() . '/.well-known/security.txt';

        return new Response(
            implode("\n", $lines) . "\n",
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]
        );
    }

    /**
     * @param array<string, array{value: string}> $settings
     */
    private function getSettingValue(
        array $settings,
        SettingName $settingName,
    ): ?string {
        $value = $settings[$settingName->value]['value'] ?? null;

        return $value === null || trim($value) === ''
            ? null
            : trim($value);
    }
}
