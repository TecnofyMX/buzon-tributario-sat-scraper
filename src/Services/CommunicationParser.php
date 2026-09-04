<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Services;

use DateTimeImmutable;
use DOMElement;
use Symfony\Component\DomCrawler\Crawler;
use Tecnofy\BuzonTributarioSatScraper\Communication;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\CommunicationStructureException;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;

final class CommunicationParser
{
    private const DATE_PATTERN = '\\d{1,2}\\/(?:[[:alpha:]áéíóú]{3,10}|\\d{1,2})\\/\\d{4}'
        . '\\s+\\d{1,2}:\\d{2}(?::\\d{2})?\\s*hrs?\\.?';

    /** @return list<Communication> */
    public function parseUnread(Page $page): array
    {
        $crawler = new Crawler($page->html, $page->uri);
        /** @var list<DOMElement> $elements */
        $elements = array_values(array_filter($crawler->filter('*')->each(
            static fn (Crawler $element): ?DOMElement => $element->getNode(0) instanceof DOMElement
                ? $element->getNode(0)
                : null,
        )));

        $unreadStart = $this->findHeading($elements, 'mensajes no leidos');
        if (null === $unreadStart) {
            throw new CommunicationStructureException('The unread communications section was not found.');
        }
        $readStart = $this->findHeading(
            $elements,
            'mensajes leidos',
            $unreadStart + 1,
        ) ?? count($elements);

        /** @var array<string, array{communication: Communication, sourceLength: int, order: int}> $found */
        $found = [];
        for ($index = $unreadStart + 1; $index < $readStart; ++$index) {
            $text = $this->clean($elements[$index]->textContent);
            if ('' === $text || $this->hasCommunicationDescendant($elements[$index])) {
                continue;
            }

            $pattern = $this->communicationPattern();
            if (1 !== preg_match($pattern, $text, $matches)) {
                continue;
            }

            $subject = $this->clean((string) preg_replace('/^[+⊞□▶\\s]+/u', '', $matches['subject']));
            if ('' === $subject) {
                throw new CommunicationStructureException('A SAT communication has an empty subject.');
            }
            $receivedAt = $this->normalizeDate(
                $matches['year'],
                $matches['month'],
                $matches['day'],
                $matches['hour'],
                $matches['minute'],
                '' === ($matches['second'] ?? '') ? '00' : $matches['second'],
            );
            $key = $receivedAt . '|' . substr($this->normalize($subject), 0, 48);
            $sourceLength = strlen($text);
            if (! isset($found[$key]) || $sourceLength < $found[$key]['sourceLength']) {
                $found[$key] = [
                    'communication' => new Communication($receivedAt, $subject),
                    'sourceLength' => $sourceLength,
                    'order' => $index,
                ];
            }
        }

        uasort(
            $found,
            static fn (array $left, array $right): int => $left['order'] <=> $right['order'],
        );

        return array_values(array_map(
            static fn (array $item): Communication => $item['communication'],
            $found,
        ));
    }

    public function recognizesCommunicationsPage(Page $page): bool
    {
        $content = $this->normalize(strip_tags($page->html));

        return str_contains($content, 'mensajes no leidos')
            && (str_contains($content, 'mis comunicados') || str_contains($content, 'mensajes leidos'));
    }

    /** @param list<DOMElement> $elements */
    private function findHeading(array $elements, string $heading, int $offset = 0): ?int
    {
        for ($index = $offset, $count = count($elements); $index < $count; ++$index) {
            if ($heading === $this->normalize($elements[$index]->textContent)) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeDate(
        string $year,
        string $month,
        string $day,
        string $hour,
        string $minute,
        string $second,
    ): string {
        $monthNumber = ctype_digit($month) ? (int) $month : $this->monthNumber($month);
        $value = sprintf('%s-%d-%s %s:%s:%s', $year, $monthNumber, $day, $hour, $minute, $second);
        $date = DateTimeImmutable::createFromFormat('!Y-n-j G:i:s', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            false === $date
            || (false !== $errors && (0 !== $errors['warning_count'] || 0 !== $errors['error_count']))
        ) {
            throw new CommunicationStructureException('A SAT communication contains an invalid date.');
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function monthNumber(string $month): int
    {
        $month = substr($this->normalize($month), 0, 3);
        $months = [
            'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'ago' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
        ];
        if (! isset($months[$month])) {
            throw new CommunicationStructureException('A SAT communication contains an invalid date.');
        }

        return $months[$month];
    }

    private function clean(string $value): string
    {
        return trim((string) preg_replace('/\\s+/u', ' ', $value));
    }

    private function hasCommunicationDescendant(DOMElement $element): bool
    {
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if (1 === preg_match($this->communicationPattern(), $this->clean($descendant->textContent))) {
                return true;
            }
        }

        return false;
    }

    private function communicationPattern(): string
    {
        return '/(?<day>\\d{1,2})\\/(?<month>[[:alpha:]áéíóú]{3,10}|\\d{1,2})'
            . '\\/(?<year>\\d{4})\\s+(?<hour>\\d{1,2}):(?<minute>\\d{2})'
            . '(?::(?<second>\\d{2}))?\\s*hrs?\\.?\\s+(?<subject>.+?)'
            . '(?=\\s+' . self::DATE_PATTERN . '|$)/iu';
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return strtolower($this->clean(strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ])));
    }
}
