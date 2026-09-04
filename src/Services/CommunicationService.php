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

            if (! $this->parser->recognizesCommunicationsPage($page)) {
                $referer = $page->uri;
                $page = $this->requester->request('GET', $frameUri ?? Url::COMMUNICATIONS_FRAME, [
                    RequestOptions::HEADERS => ['Referer' => $referer],
                ]);
            }
        }

        if (! $this->parser->recognizesCommunicationsPage($page)) {
            throw new UnexpectedPageException(sprintf(
                'The SAT communications iframe response at %s does not contain the expected headings (%s).',
                $this->withoutSensitiveComponents($page->uri),
                $this->classifyUnexpectedPage($page),
            ));
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

    private function withoutSensitiveComponents(string $uri): string
    {
        return (string) (new Uri($uri))
            ->withUserInfo('')
            ->withQuery('')
            ->withFragment('');
    }

    private function classifyUnexpectedPage(Page $page): string
    {
        $content = strtolower(html_entity_decode(strip_tags($page->html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (
            str_contains($content, 'iniciar sesión')
            || str_contains($content, 'iniciar sesion')
            || str_contains($page->html, 'name="userCaptcha"')
        ) {
            return 'the SAT returned a login page';
        }
        if (str_contains($page->html, 'SAMLResponse')) {
            return 'the SAT returned an unfinished SSO response';
        }
        if (
            str_contains($content, 'servicio no disponible')
            || str_contains($content, 'mantenimiento')
            || str_contains($content, 'ocurrió un error')
            || str_contains($content, 'ocurrio un error')
        ) {
            return 'the SAT returned an error or maintenance page';
        }

        return 'the SAT returned an unrecognized page';
    }
}
