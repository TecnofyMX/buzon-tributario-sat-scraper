<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Services;

use DateTimeImmutable;
use Symfony\Component\DomCrawler\Crawler;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\NotificationStructureException;
use Tecnofy\BuzonTributarioSatScraper\Internal\FormParser;
use Tecnofy\BuzonTributarioSatScraper\Internal\NavigationRequest;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;
use Tecnofy\BuzonTributarioSatScraper\Notification;
use Tecnofy\BuzonTributarioSatScraper\NotificationStatus;

final class NotificationParser
{
    private const DANGEROUS_WORDS = ['documento', 'ver acto', 'aceptar', 'acuse', 'firma', 'notificarme'];

    public function __construct(private FormParser $formParser)
    {
    }

    /** @return list<Notification> */
    public function parse(Page $page, ?NotificationStatus $fallbackStatus = null): array
    {
        $crawler = new Crawler($page->html, $page->uri);
        $notifications = [];

        $crawler->filter('table')->each(function (Crawler $table) use (&$notifications, $fallbackStatus): void {
            $headers = $table->filter('thead th');
            if (0 === $headers->count()) {
                $headers = $table->filter('tr')->first()->filter('th');
            }

            $map = $this->mapHeaders($headers->each(
                fn (Crawler $header): string => $this->normalize($header->text('')),
            ));
            if (null === $map) {
                return;
            }

            $tableStatus = $this->detectStatus($table->text(''), $fallbackStatus);
            $table->filter('tr')->each(function (Crawler $row) use (
                &$notifications,
                $map,
                $tableStatus,
            ): void {
                $cells = $row->filter('td');
                if (0 === $cells->count()) {
                    return;
                }

                $values = $cells->each(fn (Crawler $cell): string => $this->clean($cell->text('')));
                $largestIndex = max($map);
                if (count($values) <= $largestIndex) {
                    throw new NotificationStructureException('A SAT notification row has fewer columns than expected.');
                }

                $folio = $values[$map['folio']];
                if ('' === $folio || str_contains($this->normalize($folio), 'folio')) {
                    return;
                }

                $status = $this->detectStatus(implode(' ', $values), $tableStatus);
                if (null === $status) {
                    throw new NotificationStructureException(
                        'The status of a SAT notification could not be determined.',
                    );
                }

                $notifications[] = new Notification(
                    $folio,
                    $values[$map['authority']],
                    $values[$map['act']],
                    $this->normalizeDate($values[$map['date']]),
                    $status,
                );
            });
        });

        return $notifications;
    }

    /** @return list<NavigationRequest> */
    public function discoverNavigations(Page $page, ?NotificationStatus $fallbackStatus = null): array
    {
        $crawler = new Crawler($page->html, $page->uri);
        $requests = [];

        $crawler->filter('a[href]')->each(function (Crawler $link) use (&$requests, $page, $fallbackStatus): void {
            $label = $this->normalize($link->text(''));
            $href = trim((string) $link->attr('href'));
            if (! $this->isSafeNavigationLabel($label, $link) || ! $this->isUsableReference($href)) {
                return;
            }

            $uri = $this->formParser->resolve($page->uri, $href);
            if (! $this->isSatUri($uri) || $this->containsDangerousWord($label . ' ' . $uri)) {
                return;
            }

            $requests[] = new NavigationRequest('GET', $uri, [], $this->detectStatus($label, $fallbackStatus));
        });

        $forms = $this->formParser->extractAll($page);
        $crawler->filter('form')->each(function (
            Crawler $formNode,
            int $index
        ) use (
            &$requests,
            $forms,
            $fallbackStatus,
        ): void {
            if (! isset($forms[$index])) {
                return;
            }

            $formNode->filter('button[name], input[type="submit"][name]')->each(
                function (Crawler $button) use (&$requests, $forms, $index, $fallbackStatus): void {
                    $label = $this->normalize((string) ($button->attr('value') ?? $button->text('')));
                    if (! $this->isSafeNavigationLabel($label, $button)) {
                        return;
                    }

                    $form = $forms[$index];
                    if (
                        ! $this->isSatUri($form->action)
                        || $this->containsDangerousWord($label . ' ' . $form->action)
                    ) {
                        return;
                    }

                    $name = (string) $button->attr('name');
                    $value = (string) ($button->attr('value') ?? $name);
                    $fields = array_replace($form->fields, [$name => $value]);
                    if ($this->hasDangerousFields($fields)) {
                        return;
                    }

                    $requests[] = new NavigationRequest(
                        $form->method,
                        $form->action,
                        $fields,
                        $this->detectStatus($label, $fallbackStatus),
                    );
                },
            );
        });

        return $requests;
    }

    public function recognizesNotificationsPage(Page $page): bool
    {
        $content = $this->normalize(strip_tags($page->html));

        return str_contains($content, 'mis notificaciones')
            || str_contains($content, 'notificaciones pendientes')
            || str_contains($content, 'folio del acto administrativo');
    }

    /**
     * @param list<string> $headers
     * @return array{folio: int, authority: int, act: int, date: int}|null
     */
    private function mapHeaders(array $headers): ?array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            if (str_contains($header, 'folio')) {
                $map['folio'] = $index;
            } elseif (str_contains($header, 'autoridad')) {
                $map['authority'] = $index;
            } elseif (str_contains($header, 'acto administrativo') || str_contains($header, 'tipo de acto')) {
                $map['act'] = $index;
            } elseif (
                str_contains($header, 'fecha') && (
                    str_contains($header, 'aviso') || str_contains($header, 'notificacion') || 'fecha' === $header
                )
            ) {
                $map['date'] = $index;
            }
        }

        return isset($map['folio'], $map['authority'], $map['act'], $map['date']) ? $map : null;
    }

    private function detectStatus(string $content, ?NotificationStatus $fallback): ?NotificationStatus
    {
        $content = $this->normalize($content);
        if (str_contains($content, 'pendiente')) {
            return NotificationStatus::Pending;
        }
        if (str_contains($content, 'notificad')) {
            return NotificationStatus::Notified;
        }

        return $fallback;
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        foreach (['!d/m/Y', '!d-m-Y', '!Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if (
                false !== $date
                && (false === $errors || (0 === $errors['warning_count'] && 0 === $errors['error_count']))
            ) {
                return $date->format('Y-m-d');
            }
        }

        throw new NotificationStructureException('A SAT notification contains an invalid notice date.');
    }

    private function isSafeNavigationLabel(string $label, Crawler $node): bool
    {
        if ($this->containsDangerousWord($label)) {
            return false;
        }

        $safeLabelPattern = '/^((mis )?notificaciones|pendientes?|notificad[oa]s?'
            . '|siguiente|anterior|next|prev|[>»<«])$/';
        if (1 === preg_match($safeLabelPattern, $label)) {
            return true;
        }

        if (1 !== preg_match('/^\d+$/', $label)) {
            return false;
        }

        $parent = $node->getNode(0)?->parentNode;
        $context = '';
        for ($level = 0; $level < 3 && null !== $parent; ++$level, $parent = $parent->parentNode) {
            if ($parent instanceof \DOMElement) {
                $context .= ' ' . $parent->getAttribute('class') . ' ' . $parent->getAttribute('id');
            }
        }

        $context = $this->normalize($context);

        return str_contains($context, 'pagin') || str_contains($context, 'pager');
    }

    private function isUsableReference(string $reference): bool
    {
        return '' !== $reference
            && '#' !== $reference
            && ! str_starts_with(strtolower($reference), 'javascript:');
    }

    private function isSatUri(string $uri): bool
    {
        $host = strtolower((string) parse_url($uri, PHP_URL_HOST));

        return 'sat.gob.mx' === $host || str_ends_with($host, '.sat.gob.mx');
    }

    /** @param array<string, string> $fields */
    private function hasDangerousFields(array $fields): bool
    {
        return $this->containsDangerousWord(implode(' ', array_keys($fields)));
    }

    private function containsDangerousWord(string $value): bool
    {
        $value = $this->normalize($value);
        foreach (self::DANGEROUS_WORDS as $word) {
            if (str_contains($value, $word)) {
                return true;
            }
        }

        return false;
    }

    private function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function normalize(string $value): string
    {
        return strtolower($this->clean(strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ])));
    }
}
