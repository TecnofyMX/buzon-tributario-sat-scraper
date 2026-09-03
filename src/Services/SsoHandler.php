<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Services;

use GuzzleHttp\RequestOptions;
use Symfony\Component\DomCrawler\Crawler;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\SsoException;
use Tecnofy\BuzonTributarioSatScraper\Internal\FormParser;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;

final readonly class SsoHandler
{
    private const MAX_STEPS = 12;

    public function __construct(
        private HttpRequester $requester,
        private FormParser $formParser,
    ) {
    }

    public function handle(Page $page): Page
    {
        for ($step = 0; $step < self::MAX_STEPS; ++$step) {
            if ($this->isBuzonPage($page)) {
                return $page;
            }

            if (str_contains($page->html, 'SAMLResponse')) {
                $form = $this->formParser->extract($page, ['form']);
                $page = $this->requester->request($form->method, $form->action, [
                    RequestOptions::FORM_PARAMS => $form->fields,
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

            throw new SsoException('The SAT SSO response does not contain a safe continuation.');
        }

        throw new SsoException('The SAT SSO flow exceeded the safe redirect limit.');
    }

    private function isBuzonPage(Page $page): bool
    {
        $normalized = $this->normalize(strip_tags($page->html));

        return str_contains($normalized, 'mis notificaciones')
            || str_contains($normalized, 'mis expedientes')
            || (str_contains($page->uri, '/buzon') && ! str_contains($normalized, 'iniciar sesion'));
    }

    private function findAutomaticTarget(Page $page): ?string
    {
        $crawler = new Crawler($page->html, $page->uri);
        $frames = $crawler->filter('iframe[src*="buzon"], iframe[src*="acceso"]');
        if (0 < $frames->count()) {
            $source = $frames->first()->attr('src');
            if (null !== $source) {
                return $this->formParser->resolve($page->uri, $source);
            }
        }

        $refresh = $crawler->filter(
            'meta[http-equiv="refresh"], meta[http-equiv="Refresh"], meta[http-equiv="REFRESH"]',
        );
        if (0 < $refresh->count()) {
            $content = (string) $refresh->first()->attr('content');
            if (1 === preg_match('/url\s*=\s*["\']?([^"\']+)/i', $content, $matches)) {
                return $this->formParser->resolve($page->uri, trim($matches[1]));
            }
        }

        $links = $crawler->filter('a[href*="/buzon"], a[href*="mis-notificaciones"]');
        if (0 < $links->count()) {
            $href = $links->first()->attr('href');
            if (null !== $href && ! str_starts_with($href, 'javascript:')) {
                return $this->formParser->resolve($page->uri, $href);
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return strtolower(strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']));
    }
}
