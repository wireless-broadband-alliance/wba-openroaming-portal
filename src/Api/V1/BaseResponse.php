<?php

namespace App\Api\V1;

use Symfony\Component\HttpFoundation\JsonResponse;

class BaseResponse
{
    public function __construct(
        private readonly int $statusCode,
        private readonly mixed $data = null,
        private readonly ?string $error = null,
        private readonly mixed $headers = []
    ) {
    }

    public function toResponse(): JsonResponse
    {
        if ($this->error) {
            $response = [
                'success' => false,
                'error' => $this->error
            ];

            if (!empty($this->data)) {
                $response = array_merge($response, $this->data);
            }

            return new JsonResponse($response, $this->statusCode, $this->headers);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $this->data
        ], $this->statusCode, $this->headers);
    }
}
