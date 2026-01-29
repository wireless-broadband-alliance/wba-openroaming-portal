<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class DomainListUrlValidator extends ConstraintValidator
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof DomainListUrl) {
            throw new UnexpectedTypeException($constraint, DomainListUrl::class);
        }

        if (!$value) {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->violation($constraint);
            return;
        }

        try {
            $response = $this->httpClient->request('GET', $value, [
                'max_redirects' => 3,
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->violation($constraint);
                return;
            }

            $headers = $response->getHeaders(false);
            $contentType = $headers['content-type'][0] ?? '';

            // Reject HTML pages (GitHub UI, etc.)
            if (str_contains($contentType, 'text/html')) {
                $this->violation($constraint);
                return;
            }

            $content = substr($response->getContent(false), 0, 2000);

            $lines = array_filter(
                array_map('trim', explode("\n", $content)),
                static fn($line) => $line !== '' && !str_starts_with($line, '#')
            );

            if ($lines === []) {
                $this->violation($constraint);
                return;
            }

            $validLines = 0;

            foreach ($lines as $line) {
                if (preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/i', $line)) {
                    $validLines++;
                }
            }

            if ($validLines < 3) {
                $this->violation($constraint);
            }
        } catch (Throwable) {
            $this->violation($constraint);
        }
    }

    private function violation(DomainListUrl $constraint): void
    {
        $this->context
            ->buildViolation($constraint->message)
            ->addViolation();
    }
}
