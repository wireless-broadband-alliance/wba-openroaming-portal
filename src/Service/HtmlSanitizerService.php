<?php

namespace App\Service;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

readonly class HtmlSanitizerService
{
  public function __construct(
      private HtmlSanitizerInterface $sanitizer
  ) {
  }

  public function sanitize(string $html): string
  {
    return $this->sanitizer->sanitize($html);
  }
}
