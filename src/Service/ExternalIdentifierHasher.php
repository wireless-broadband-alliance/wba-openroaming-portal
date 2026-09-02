<?php

declare(strict_types=1);

namespace App\Service;

use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ExternalIdentifierHasher
{
    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        #[SensitiveParameter]
        private string $appSecret
    ) {
    }

    public function hash(string $identifier): string
    {
        return hash_hmac('sha256', $identifier, $this->appSecret);
    }
}
