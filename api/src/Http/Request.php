<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    private string $method;
    private string $path;
    private array $queryParams;
    private array $headers;
    private string $rawBody;
    private array $jsonBody;
    private ?string $jsonError;

    private function __construct(
        string $method,
        string $path,
        array $queryParams,
        array $headers,
        string $rawBody,
        array $jsonBody,
        ?string $jsonError
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->queryParams = $queryParams;
        $this->headers = $headers;
        $this->rawBody = $rawBody;
        $this->jsonBody = $jsonBody;
        $this->jsonError = $jsonError;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $path = self::normalisePath($path);

        $queryString = parse_url($uri, PHP_URL_QUERY) ?? '';
        $queryParams = [];
        if ($queryString !== '') {
            parse_str($queryString, $queryParams);
        }

        $headers = self::collectHeaders();
        $rawBody = (string) file_get_contents('php://input');

        $jsonBody = [];
        $jsonError = null;

        if (trim($rawBody) !== '') {
            $decoded = json_decode($rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $jsonBody = $decoded;
            } else {
                $jsonError = json_last_error_msg();
            }
        }

        return new self(
            strtoupper($method),
            $path,
            $queryParams,
            $headers,
            $rawBody,
            $jsonBody,
            $jsonError
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function getQueryParam(string $name, $default = null)
    {
        return $this->queryParams[$name] ?? $default;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $key = strtolower($name);

        return $this->headers[$key] ?? null;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getJsonBody(): array
    {
        return $this->jsonBody;
    }

    public function hasJsonError(): bool
    {
        return $this->jsonError !== null;
    }

    public function getJsonError(): ?string
    {
        return $this->jsonError;
    }

    private static function normalisePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        $normalised = '/' . ltrim($path, '/');
        if ($normalised !== '/' && substr($normalised, -1) === '/') {
            $normalised = rtrim($normalised, '/');
        }

        return $normalised;
    }

    private static function collectHeaders(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $serverHeaders = getallheaders();
            if ($serverHeaders !== false) {
                foreach ($serverHeaders as $name => $value) {
                    $headers[strtolower($name)] = $value;
                }
            }
        }

        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $headerName = strtolower(str_replace('_', '-', substr($name, 5)));
                $headers[$headerName] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }
}
