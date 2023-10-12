<?php

namespace App\Api\V1;

use Symfony\Component\HttpFoundation\JsonResponse;

class BaseResponse
{
    /** @var int */
    private $statusCode;

    /** @var mixed */
    private $data;

    /** @var string|null */
    private $error;

    private $headers;

    public function __construct(int $statusCode, $data = null, string $error = null, $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->error = $error;
        $this->headers = $headers;
    }

    public function toResponse(): JsonResponse
    {
        if ($this->error) {
            return new JsonResponse([
                'success' => false,
                'error' => $this->error
            ], $this->statusCode, $this->headers);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $this->data
        ], $this->statusCode, $this->headers);
    }
}
