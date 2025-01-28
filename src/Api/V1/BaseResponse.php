<?php

namespace App\Api\V1;

use Symfony\Component\HttpFoundation\JsonResponse;

class BaseResponse
{
    /** @var int */
    private int $statusCode;

    /** @var mixed */
    private mixed $data;

    /** @var string|null */
    private ?string $error;

    private mixed $headers;

    public function __construct(int $statusCode, $data = null, ?string $error = null, $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->error = $error;
        $this->headers = $headers;
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
