<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DomCrawler\Crawler;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\SsoException;
use Tecnofy\BuzonTributarioSatScraper\Internal\Form;
use Tecnofy\BuzonTributarioSatScraper\Internal\FormParser;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;

final class SsoHandler
{
    private const MAX_STEPS = 12;

    private FormParser $formParser;

    public function __construct(private HttpRequester $requester)
    {
        $this->formParser = new FormParser();
    }

    public function handle(Page $page): Page
    {
        for ($step = 0; $step < self::MAX_STEPS; ++$step) {
            if ($this->isAuthenticatedSatellitePage($page)) {
                return $page;
            }

            if (str_contains($page->html, 'SAMLResponse') || str_contains($page->html, 'SAMLRequest')) {
                $form = $this->formParser->extract($page, ['form']);
                $page = $this->requester->request($form->method, $form->action, [
                    RequestOptions::FORM_PARAMS => $form->fields,
                    RequestOptions::HEADERS => ['Referer' => $page->uri],
                ]);
                continue;
            }

            $automaticForm = $this->findAutomaticForm($page);
            if (null !== $automaticForm) {
                $page = $this->requester->request($automaticForm->method, $automaticForm->action, [
                    RequestOptions::FORM_PARAMS => $automaticForm->fields,
                    RequestOptions::HEADERS => ['Referer' => $page->uri],
                ]);
                continue;
            }

            $target = $this->findAutomaticTarget($page);
            if (null !== $target) {
                $page = $this->requester->request('GET', $target, [
                    RequestOptions::HEADERS => ['Referer' => $page->uri],
                ]);
                continue;
            }

            return $page;
        }

        throw new SsoException('The SAT SSO flow exceeded the safe redirect limit.');
    }

    private function isAuthenticatedSatellitePage(Page $page): bool
    {
        $normalized = $this->normalize(strip_tags($page->html));

        return str_contains($normalized, 'mis comunicados')
            || str_contains($normalized, 'mis expedientes')
            || str_contains($normalized, 'mensajes no leidos')
            || (str_contains($page->uri, '/buzon') && ! str_contains($normalized, 'iniciar sesion'));
    }

    private function findAutomaticForm(Page $page): ?Form
    {
        if (1 !== preg_match('/(?:\.submit\s*\(|onload\s*=)/i', $page->html)) {
            return null;
        }

        $crawler = new Crawler($page->html, $page->uri);
        $crawlerForms = $crawler->filter('form');
        $forms = $this->formParser->extractAll($page);
        foreach ($forms as $index => $form) {
            $crawlerForm = $crawlerForms->eq($index);
            $interactiveControls = $crawlerForm->filter(
                'input:not([type]), input:not([type="hidden"]), textarea, select, button',
            );
            if (0 === $interactiveControls->count() && $this->isTrustedSatUri($form->action)) {
                return $form;
            }
        }

        return null;
    }

    private function findAutomaticTarget(Page $page): ?string
    {
        $crawler = new Crawler($page->html, $page->uri);
        $frames = $crawler->filter('iframe[src*="buzon"], iframe[src*="acceso"]');
        if (0 < $frames->count()) {
            $source = $frames->first()->attr('src');
            if (null !== $source) {
                return $this->trustedTarget($page->uri, $source);
            }
        }

        $refresh = $crawler->filter(
            'meta[http-equiv="refresh"], meta[http-equiv="Refresh"], meta[http-equiv="REFRESH"]',
        );
        if (0 < $refresh->count()) {
            $content = (string) $refresh->first()->attr('content');
            if (1 === preg_match('/url\s*=\s*["\']?([^"\']+)/i', $content, $matches)) {
                return $this->trustedTarget($page->uri, trim($matches[1]));
            }
        }

        $javascriptPatterns = [
            '/\b(?:window\.)?(?:top\.)?(?:document\.)?location(?:\.href)?\s*=\s*["\']([^"\']+)["\']/i',
            '/\b(?:window\.)?(?:top\.)?(?:document\.)?location\.(?:assign|replace)'
                . '\(\s*["\']([^"\']+)["\']/i',
        ];
        foreach ($javascriptPatterns as $pattern) {
            if (1 === preg_match($pattern, $page->html, $matches)) {
                return $this->trustedTarget($page->uri, html_entity_decode($matches[1]));
            }
        }

        $links = $crawler->filter('a[href*="/buzon"], a[href*="mis-comunicados"]');
        if (0 < $links->count()) {
            $href = $links->first()->attr('href');
            if (null !== $href && ! str_starts_with($href, 'javascript:')) {
                return $this->trustedTarget($page->uri, $href);
            }
        }

        return null;
    }

    private function trustedTarget(string $baseUri, string $reference): ?string
    {
        $resolved = $this->formParser->resolve($baseUri, $reference);

        return $this->isTrustedSatUri($resolved) ? $resolved : null;
    }

    private function isTrustedSatUri(string $uri): bool
    {
        $parsed = new Uri($uri);
        $host = strtolower($parsed->getHost());

        return 'https' === strtolower($parsed->getScheme())
            && ('sat.gob.mx' === $host || str_ends_with($host, '.sat.gob.mx'));
    }

    private function normalize(string $value): string
    {
        return strtolower(strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']));
    }
}
