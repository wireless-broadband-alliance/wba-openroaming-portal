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

    public function validate(mixed $value, Constraint $constraint): void
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

            // Read only a small chunk (performance)
            $content = substr($response->getContent(false), 0, 3000);
            $content = trim($content);

            if ($content === '') {
                $this->violation($constraint);
                return;
            }

            // Reject real HTML documents (GitHub UI, etc.)
            if ($this->looksLikeHtmlDocument($content)) {
                $this->violation($constraint);
                return;
            }

            // JSON array
            if ($this->looksLikeJsonArray($content)) {
                return;
            }

            // TXT / CSV validation
            $lines = array_filter(
                array_map(trim(...), preg_split('/\R/', $content)),
                static fn($line) => $line !== '' && !str_starts_with((string) $line, '#')
            );

            if ($lines === []) {
                $this->violation($constraint);
                return;
            }

            $validLines = 0;

            foreach ($lines as $line) {
                if (
                    $this->isDomain($line) ||
                    $this->isCidr($line)
                ) {
                    $validLines++;
                }
            }

            // Require some signal, not perfection
            if ($validLines < 3) {
                $this->violation($constraint);
            }
        } catch (Throwable) {
            $this->violation($constraint);
        }
    }

    private function looksLikeHtmlDocument(string $content): bool
    {
        return preg_match('/<(html|head|body|script|title)[\s>]/i', $content) === 1;
    }

    private function looksLikeJsonArray(string $content): bool
    {
        if (!str_starts_with($content, '[')) {
            return false;
        }

        $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        return is_array($json);
    }

    private function isDomain(string $line): bool
    {
        return preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/i', $line) === 1;
    }

    private function isCidr(string $line): bool
    {
        return preg_match(
            '/^(?:\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/',
            $line
        ) === 1;
    }

    private function violation(DomainListUrl $constraint): void
    {
        $this->context
            ->buildViolation($constraint->message)
            ->addViolation();
    }
}
