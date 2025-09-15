<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class StreamResponse implements ResponseInterface
{
    /**
     * @var callable(self): void
     */
    private $emitter;

    private int $status;

    /**
     * @var array<string, string>
     */
    private array $headers;

    /**
     * @param callable(self): void $emitter
     * @param array<string, string> $headers
     */
    public function __construct(callable $emitter, int $status = 200, array $headers = [])
    {
        $this->emitter = $emitter;
        $this->status = $status;
        $this->headers = $headers;
    }

    public function send(): void
    {
        http_response_code($this->status);

        $headers = array_merge(
            [
                'Content-Type' => 'text/event-stream; charset=utf-8',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
            $this->headers
        );

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
        }

        ignore_user_abort(true);

        ($this->emitter)($this);

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendEvent(string $event, array $data): void
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Failed to encode stream payload: ' . json_last_error_msg());
        }

        echo 'event: ' . $event . "\n";
        echo 'data: ' . $encoded . "\n\n";

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }
}
