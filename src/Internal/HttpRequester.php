<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Internal;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
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
            throw new NetworkException('The SAT request failed.', 0, $exception);
        }

        $history = $response->getHeader('X-Guzzle-Redirect-History');
        $effectiveUri = [] === $history ? $uri : (string) end($history);

        return new Page((string) $response->getBody(), $effectiveUri);
    }
}
