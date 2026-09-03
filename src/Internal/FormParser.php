<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Internal;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Symfony\Component\DomCrawler\Crawler;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\UnexpectedPageException;

final class FormParser
{
    /** @param list<string> $selectors */
    public function extract(Page $page, array $selectors): Form
    {
        $crawler = new Crawler($page->html, $page->uri);
        foreach ($selectors as $selector) {
            $forms = $crawler->filter($selector);
            if (0 === $forms->count()) {
                continue;
            }

            return $this->fromCrawler($forms->first(), $page->uri);
        }

        throw new UnexpectedPageException('The expected form was not found in the SAT response.');
    }

    public function resolve(string $baseUri, string $reference): string
    {
        return (string) UriResolver::resolve(new Uri($baseUri), new Uri(html_entity_decode($reference)));
    }

    /** @return list<Form> */
    public function extractAll(Page $page): array
    {
        $crawler = new Crawler($page->html, $page->uri);
        /** @var list<Form> $forms */
        $forms = [];
        $crawler->filter('form')->each(function (Crawler $form) use (&$forms, $page): void {
            $forms[] = $this->fromCrawler($form, $page->uri);
        });

        return $forms;
    }

    private function fromCrawler(Crawler $form, string $baseUri): Form
    {
        $action = (string) ($form->attr('action') ?? $baseUri);
        $method = strtoupper((string) ($form->attr('method') ?? 'GET'));
        $fields = [];

        $form->filter('input[name], textarea[name], select[name]')->each(
            static function (Crawler $control) use (&$fields): void {
                $name = (string) $control->attr('name');
                if ('' === $name || null !== $control->attr('disabled')) {
                    return;
                }

                $type = strtolower((string) ($control->attr('type') ?? ''));
                if (in_array($type, ['submit', 'button', 'image', 'file', 'reset'], true)) {
                    return;
                }

                if (in_array($type, ['checkbox', 'radio'], true) && null === $control->attr('checked')) {
                    return;
                }

                if ('select' === strtolower($control->nodeName())) {
                    $selected = $control->filter('option[selected]');
                    $option = 0 < $selected->count() ? $selected->first() : $control->filter('option')->first();
                    $fields[$name] = 0 < $option->count()
                        ? (string) ($option->attr('value') ?? $option->text(''))
                        : '';
                    return;
                }

                $fields[$name] = 'textarea' === strtolower($control->nodeName())
                    ? $control->text('')
                    : (string) ($control->attr('value') ?? '');
            },
        );

        return new Form($this->resolve($baseUri, $action), $method, $fields);
    }
}
