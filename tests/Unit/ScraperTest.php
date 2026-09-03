<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Tests\Unit;

use ArrayObject;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PhpCfdi\ImageCaptchaResolver\CaptchaAnswer;
use PhpCfdi\ImageCaptchaResolver\CaptchaAnswerInterface;
use PhpCfdi\ImageCaptchaResolver\CaptchaImageInterface;
use PhpCfdi\ImageCaptchaResolver\CaptchaResolverInterface;
use Psr\Http\Message\RequestInterface;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\NotificationStructureException;
use Tecnofy\BuzonTributarioSatScraper\HttpClientFactory;
use Tecnofy\BuzonTributarioSatScraper\Scraper;
use Tecnofy\BuzonTributarioSatScraper\Tests\TestCase;

final class ScraperTest extends TestCase
{
    public function testCompleteWorkflowLogsOutAndNeverOpensDocuments(): void
    {
        /** @var ArrayObject<int, RequestInterface> $requests */
        $requests = new ArrayObject();
        $mock = new MockHandler([
            new Response(200, ['Set-Cookie' => 'SATSESSID=sanitized; Path=/'], self::fixture('login-portal.html')),
            new Response(200, [], self::fixture('login-form.html')),
            new Response(200, [], self::fixture('saml.html')),
            new Response(200, [], self::fixture('buzon-home.html')),
            new Response(200, [], self::fixture('notifications-pending.html')),
            new Response(200, [], self::fixture('notifications-pending-page-2.html')),
            new Response(200, [], self::fixture('notifications-notified.html')),
            new Response(200, [], '<html><body>Sesión cerrada</body></html>'),
            new Response(200, [], '<html><body>Sesión cerrada</body></html>'),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::tap(
            static function (RequestInterface $request) use ($requests): void {
                $requests[] = $request;
            },
        ));
        $client = HttpClientFactory::create(['handler' => $stack]);
        $resolver = new class implements CaptchaResolverInterface {
            public function resolve(CaptchaImageInterface $image): CaptchaAnswerInterface
            {
                return new CaptchaAnswer('ABCDE');
            }
        };

        $result = Scraper::create($client, $resolver, 'AAA010101AAA', 'secret')->notifications();

        self::assertCount(3, $result);
        self::assertCount(9, $requests);
        $secondRequest = $requests[1];
        if (! $secondRequest instanceof RequestInterface) {
            self::fail('The second request was not recorded.');
        }
        self::assertTrue($secondRequest->hasHeader('Cookie'));
        $requestedUris = [];
        foreach ($requests as $request) {
            $requestedUris[] = (string) $request->getUri();
        }
        $requestedUris = implode(' ', $requestedUris);
        self::assertStringNotContainsString('/documento/', $requestedUris);
        self::assertStringNotContainsString('/acuse/', $requestedUris);
        self::assertStringContainsString('/personas/cerrar-sesion', $requestedUris);
        self::assertStringContainsString('/nidp/app/logout', $requestedUris);
    }

    public function testOriginalCollectionFailureWinsOverLogoutFailure(): void
    {
        $invalidNotifications = <<<'HTML'
            <html lang="es"><body><h1>Mis notificaciones</h1>
            <table><caption>Pendientes</caption>
            <tr><th>Folio</th><th>Autoridad</th><th>Acto administrativo</th><th>Fecha de aviso</th></tr>
            <tr><td>ABC-ERROR</td><td>SAT</td><td>Acto</td><td>not-a-date</td></tr>
            </table></body></html>
            HTML;
        $mock = new MockHandler([
            new Response(200, [], self::fixture('login-portal.html')),
            new Response(200, [], self::fixture('login-form.html')),
            new Response(200, [], self::fixture('saml.html')),
            new Response(200, [], self::fixture('buzon-home.html')),
            new Response(200, [], $invalidNotifications),
            new Response(500, [], 'logout unavailable'),
        ]);
        $client = HttpClientFactory::create(['handler' => HandlerStack::create($mock)]);
        $resolver = new class implements CaptchaResolverInterface {
            public function resolve(CaptchaImageInterface $image): CaptchaAnswerInterface
            {
                return new CaptchaAnswer('ABCDE');
            }
        };

        $this->expectException(NotificationStructureException::class);
        Scraper::create($client, $resolver, 'AAA010101AAA', 'secret')->notifications();
    }
}
