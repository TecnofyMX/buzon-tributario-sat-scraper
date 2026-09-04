<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Internal;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\NetworkException;

final class HttpRequester
{
    public function __construct(private ClientInterface $client)
    {
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $uri, array $options = []): Page
    {
        $redirects = $options[RequestOptions::ALLOW_REDIRECTS] ?? [];
        if (is_array($redirects)) {
            $options[RequestOptions::ALLOW_REDIRECTS] = array_replace([
                'max' => 10,
                'strict' => true,
                'referer' => true,
                'track_redirects' => true,
            ], $redirects);
        }

        try {
            $response = $this->client->request($method, $uri, $options);
        } catch (GuzzleException $exception) {
            throw new NetworkException(sprintf(
                'The SAT %s request to %s failed%s.',
                strtoupper($method),
                $this->withoutSensitiveComponents($uri),
                $this->failureContext($exception),
            ), 0, $exception);
        }

        $history = $response->getHeader('X-Guzzle-Redirect-History');
        $effectiveUri = [] === $history ? $uri : (string) end($history);

        return new Page((string) $response->getBody(), $effectiveUri);
    }

    private function withoutSensitiveComponents(string $uri): string
    {
        return (string) (new Uri($uri))
            ->withUserInfo('')
            ->withQuery('')
            ->withFragment('');
    }

    private function failureContext(GuzzleException $exception): string
    {
        if ($exception instanceof RequestException && null !== $exception->getResponse()) {
            return sprintf(' with HTTP status %d', $exception->getResponse()->getStatusCode());
        }

        $handlerContext = $exception instanceof ConnectException || $exception instanceof RequestException
            ? $exception->getHandlerContext()
            : [];
        $errorNumber = $handlerContext['errno'] ?? null;
        if (! is_int($errorNumber)) {
            return '';
        }

        $reason = match ($errorNumber) {
            6 => 'DNS resolution failed',
            7 => 'connection failed',
            28 => 'request timed out',
            35 => 'TLS handshake failed',
            60 => 'TLS certificate validation failed',
            default => 'transport error',
        };

        return sprintf(' with cURL error %d (%s)', $errorNumber, $reason);
    }
}
