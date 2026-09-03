<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DomCrawler\Crawler;
use Tecnofy\BuzonTributarioSatScraper\CommunicationCollection;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\UnexpectedPageException;
use Tecnofy\BuzonTributarioSatScraper\Internal\FormParser;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;
use Tecnofy\BuzonTributarioSatScraper\Url;

final class CommunicationService
{
    private FormParser $formParser;

    public function __construct(
        private HttpRequester $requester,
        private CommunicationParser $parser,
    ) {
        $this->formParser = new FormParser();
    }

    public function collectUnread(Page $authenticatedPage): CommunicationCollection
    {
        $page = $authenticatedPage;
        if (! $this->parser->recognizesCommunicationsPage($page)) {
            $frameUri = $this->findCommunicationsFrameUri($page);
            if (null === $frameUri) {
                $page = $this->requester->request('GET', Url::COMMUNICATIONS, [
                    RequestOptions::HEADERS => ['Referer' => $authenticatedPage->uri],
                ]);
                $frameUri = $this->findCommunicationsFrameUri($page);
            }

            if (null !== $frameUri && ! $this->parser->recognizesCommunicationsPage($page)) {
                $referer = $page->uri;
                $page = $this->requester->request('GET', $frameUri, [
                    RequestOptions::HEADERS => ['Referer' => $referer],
                ]);
            }
        }

        if (! $this->parser->recognizesCommunicationsPage($page)) {
            throw new UnexpectedPageException('The SAT response does not contain Mis comunicados.');
        }

        return new CommunicationCollection(...$this->parser->parseUnread($page));
    }

    private function findCommunicationsFrameUri(Page $page): ?string
    {
        $crawler = new Crawler($page->html, $page->uri);
        $frames = $crawler->filter('iframe[src]');
        for ($index = 0, $count = $frames->count(); $index < $count; ++$index) {
            $frame = $frames->eq($index);
            $source = trim((string) $frame->attr('src'));
            if (
                '' === $source
                || (
                    'iframetoload' !== strtolower((string) $frame->attr('id'))
                    && ! str_contains(strtolower($source), '/webcomunicados/')
                )
            ) {
                continue;
            }

            $resolved = $this->formParser->resolve($page->uri, $source);
            if ($this->isTrustedSatUri($resolved)) {
                return $resolved;
            }
        }

        return null;
    }

    private function isTrustedSatUri(string $uri): bool
    {
        $parsed = new Uri($uri);
        $host = strtolower($parsed->getHost());

        return 'https' === strtolower($parsed->getScheme())
            && ('sat.gob.mx' === $host || str_ends_with($host, '.sat.gob.mx'));
    }
}
