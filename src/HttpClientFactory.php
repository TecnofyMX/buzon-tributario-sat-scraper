<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\RequestOptions;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\HttpClientInvalidOption;

final class HttpClientFactory
{
    /** @param array<string, mixed> $customOptions */
    public function __construct(private readonly array $customOptions = [])
    {
    }

    /** @param array<string, mixed> $options */
    public static function create(array $options = []): ClientInterface
    {
        return (new self($options))->build();
    }

    public function build(): ClientInterface
    {
        $options = $this->buildOptions();
        $this->checkOptions($options);

        return new Client($options);
    }

    /** @return array<string, mixed> */
    public function buildOptions(): array
    {
        $options = $this->defaultOptions();
        foreach ($this->customOptions as $name => $value) {
            if (
                in_array($name, [RequestOptions::HEADERS, RequestOptions::ALLOW_REDIRECTS], true)
                && is_array($value)
                && isset($options[$name])
                && is_array($options[$name])
            ) {
                $options[$name] = array_replace($options[$name], $value);
                continue;
            }

            $options[$name] = $value;
        }

        return $options;
    }

    /** @return array<string, mixed> */
    private function defaultOptions(): array
    {
        return [
            RequestOptions::COOKIES => new CookieJar(),
            RequestOptions::CONNECT_TIMEOUT => 20,
            RequestOptions::TIMEOUT => 120,
            RequestOptions::VERIFY => true,
            RequestOptions::HEADERS => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'es-MX,es;q=0.9',
                'User-Agent' => 'tecnofy/buzon-tributario-sat-scraper',
            ],
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => 10,
                'strict' => true,
                'referer' => true,
                'track_redirects' => true,
            ],
        ];
    }

    /** @param array<string, mixed> $options */
    private function checkOptions(array $options): void
    {
        if (! ($options[RequestOptions::COOKIES] ?? null) instanceof CookieJarInterface) {
            throw new HttpClientInvalidOption('The HTTP client must use a CookieJarInterface instance.');
        }

        if (false === ($options[RequestOptions::ALLOW_REDIRECTS] ?? null)) {
            throw new HttpClientInvalidOption('Redirect handling cannot be disabled.');
        }
    }
}
