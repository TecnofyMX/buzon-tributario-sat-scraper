<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Tests\Unit\Services;

use Tecnofy\BuzonTributarioSatScraper\Communication;
use Tecnofy\BuzonTributarioSatScraper\CommunicationCollection;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;
use Tecnofy\BuzonTributarioSatScraper\Services\CommunicationParser;
use Tecnofy\BuzonTributarioSatScraper\Tests\TestCase;

final class CommunicationParserTest extends TestCase
{
    public function testParsesOnlyUnreadCommunicationsWithoutHiddenDetails(): void
    {
        $parser = new CommunicationParser();
        $page = new Page(
            self::fixture('communications.html'),
            'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-comunicados/',
        );

        $communications = $parser->parseUnread($page);

        self::assertCount(3, $communications);
        self::assertSame('2026-09-01 16:22:00', $communications[0]->receivedAt);
        self::assertSame(
            'Registro o actualización de medios de contacto LOVL870603AJ70003',
            $communications[0]->subject,
        );
        self::assertSame('2026-09-01 16:18:00', $communications[2]->receivedAt);
        self::assertStringNotContainsString('Detalle', $communications[0]->subject);
        self::assertStringNotContainsString('26/nov/2018', implode(' ', array_map(
            static fn (Communication $item): string => $item->subject,
            $communications,
        )));
    }

    public function testEmptyUnreadSectionReturnsEmptyCollection(): void
    {
        $parser = new CommunicationParser();
        $page = new Page(
            self::fixture('communications-empty.html'),
            'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-comunicados/',
        );

        self::assertSame([], $parser->parseUnread($page));
    }

    public function testCommunicationsCollectionCanBeSerializedToJson(): void
    {
        $parser = new CommunicationParser();
        $page = new Page(
            self::fixture('communications.html'),
            'https://aplicacionesc.mat.sat.gob.mx/WebComunicados/Comunicados.aspx',
        );

        $communications = new CommunicationCollection(...$parser->parseUnread($page));
        /** @var list<array{receivedAt: string, subject: string}> $decoded */
        $decoded = json_decode(json_encode($communications, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        self::assertCount(3, $decoded);
        self::assertSame('2026-09-01 16:22:00', $decoded[0]['receivedAt']);
        self::assertSame(
            'Registro o actualización de medios de contacto LOVL870603AJ70003',
            $decoded[0]['subject'],
        );
    }
}
