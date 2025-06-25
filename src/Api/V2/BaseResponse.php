<?php

namespace App\Api\V2;

use Symfony\Component\HttpFoundation\JsonResponse;

readonly class BaseResponse
{
    public function __construct(
        private int $statusCode,
        private mixed $data = null,
        private ?string $error = null,
        private mixed $headers = []
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
