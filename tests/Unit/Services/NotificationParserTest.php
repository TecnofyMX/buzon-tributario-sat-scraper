<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Tests\Unit\Services;

use Tecnofy\BuzonTributarioSatScraper\Exceptions\NotificationStructureException;
use Tecnofy\BuzonTributarioSatScraper\Internal\FormParser;
use Tecnofy\BuzonTributarioSatScraper\Internal\NavigationRequest;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;
use Tecnofy\BuzonTributarioSatScraper\NotificationStatus;
use Tecnofy\BuzonTributarioSatScraper\Services\NotificationParser;
use Tecnofy\BuzonTributarioSatScraper\Tests\TestCase;

final class NotificationParserTest extends TestCase
{
    private NotificationParser $parser;

    protected function setUp(): void
    {
        $this->parser = new NotificationParser(new FormParser());
    }

    public function testParsesAndNormalizesPendingNotification(): void
    {
        $page = new Page(self::fixture('notifications-pending.html'), 'https://wwwmat.sat.gob.mx/buzon');
        $notifications = $this->parser->parse($page);

        self::assertCount(1, $notifications);
        self::assertSame('ABC-001', $notifications[0]->folio);
        self::assertSame('2026-08-31', $notifications[0]->noticeDate);
        self::assertSame(NotificationStatus::Pending, $notifications[0]->status);
    }

    public function testDangerousDocumentAndReceiptLinksAreNeverDiscovered(): void
    {
        $pending = new Page(self::fixture('notifications-pending.html'), 'https://wwwmat.sat.gob.mx/buzon');
        $notified = new Page(self::fixture('notifications-notified.html'), 'https://wwwmat.sat.gob.mx/buzon');
        $requests = [
            ...$this->parser->discoverNavigations($pending),
            ...$this->parser->discoverNavigations($notified),
        ];
        $serialized = implode(' ', array_map(
            static fn (NavigationRequest $request): string => $request->uri,
            $requests,
        ));

        self::assertStringNotContainsString('/abrir-documento/', $serialized);
        self::assertStringNotContainsString('/documento/', $serialized);
        self::assertStringNotContainsString('/acuse/', $serialized);
    }

    public function testExternalAndJavascriptNavigationAreIgnored(): void
    {
        $html = <<<'HTML'
            <html lang="es"><body>
            <h1>Mis notificaciones</h1>
            <a href="https://example.com/notificadas">Notificadas</a>
            <a href="javascript:openDocument()">Pendientes</a>
            </body></html>
            HTML;
        $requests = $this->parser->discoverNavigations(new Page(
            $html,
            'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-notificaciones',
        ));

        self::assertSame([], $requests);
    }

    public function testInvalidDateRaisesStructureExceptionWithoutIncludingHtml(): void
    {
        $html = <<<'HTML'
            <html lang="es"><body><h1>Mis notificaciones</h1>
            <table><caption>Pendientes</caption>
            <tr><th>Folio</th><th>Autoridad</th><th>Acto administrativo</th><th>Fecha de aviso</th></tr>
            <tr><td>SECRET-123</td><td>SAT</td><td>Acto</td><td>fecha inválida</td></tr>
            </table></body></html>
            HTML;

        try {
            $this->parser->parse(new Page($html, 'https://wwwmat.sat.gob.mx/buzon'));
            self::fail('An invalid date must raise an exception.');
        } catch (NotificationStructureException $exception) {
            self::assertStringNotContainsString('SECRET-123', $exception->getMessage());
            self::assertStringNotContainsString('fecha inválida', $exception->getMessage());
        }
    }
}
