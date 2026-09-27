<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/** Un registro que se queda con los mensajes, ya con sus {marcadores} rellenos. */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string}> */
    private array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof \Stringable) {
                $replacements['{'.$key.'}'] = (string) $value;
            }
        }

        $this->records[] = ['level' => (string) $level, 'message' => strtr((string) $message, $replacements)];
    }

    /** @return list<string> */
    public function errors(): array
    {
        return array_values(array_column(array_filter(
            $this->records,
            static fn (array $record): bool => in_array($record['level'], [LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY], true),
        ), 'message'));
    }
}
