<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class SensitiveDataProcessor implements ProcessorInterface
{
    /**
     * @var list<string>
     */
    private array $sensitiveKeys = [
        'password',
        'token',
        'secret',
        'bearer',
        'authorization',
    ];

    public function __construct(private readonly int $maxDepth = 10)
    {
    }

    /**
     * @param LogRecord|array<string, mixed> $record
     * @return LogRecord|array<string, mixed>
     */
    public function __invoke(LogRecord|array $record): LogRecord|array
    {
        if ($record instanceof LogRecord) {
            $context = $this->redact($record->context);
            $extra = $this->redact($record->extra);

            return $record->with(context: $context, extra: $extra);
        }

        $record['context'] = $this->redact($record['context'] ?? []);
        $record['extra'] = $this->redact($record['extra'] ?? []);

        return $record;
    }

    /**
     * @param array<mixed, mixed> $data
     * @return array<mixed, mixed>
     */
    private function redact(array $data, int $depth = 0): array
    {
        if ($depth >= $this->maxDepth) {
            return ['[MAX_DEPTH_REACHED]'];
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redact($value, $depth + 1);
            } elseif (is_string($key) && $this->isSensitiveKey($key)) {
                $data[$key] = 'REDACTED';
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        return array_any($this->sensitiveKeys, fn($pattern) => stripos($key, (string) $pattern) !== false);
    }
}
