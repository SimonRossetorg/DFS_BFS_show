<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class JsonResponse implements ResponseInterface
{
    private array $data;
    private int $status;
    private array $headers;

    public function __construct(array $data, int $status = 200, array $headers = [])
    {
        $this->data = $data;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function error(string $message, int $status = 400): self
    {
        return new self(['error' => $message], $status);
    }

    public function send(): void
    {
        http_response_code($this->status);

        $headers = array_merge(['Content-Type' => 'application/json'], $this->headers);
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        $encoded = json_encode(
            $this->data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($encoded === false) {
            $message = json_last_error_msg();
            throw new RuntimeException('Failed to encode JSON response: ' . $message);
        }

        echo $encoded;
    }
}
