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
use Tecnofy\BuzonTributarioSatScraper\HttpClientFactory;
use Tecnofy\BuzonTributarioSatScraper\Scraper;
use Tecnofy\BuzonTributarioSatScraper\Tests\TestCase;

final class ScraperTest extends TestCase
{
    public function testCompleteWorkflowUsesDirectLoginAndOnlyCollectsCommunications(): void
    {
        /** @var ArrayObject<int, RequestInterface> $requests */
        $requests = new ArrayObject();
        $mock = new MockHandler([
            new Response(200, ['Set-Cookie' => 'JSESSIONID=sanitized; Path=/'], '<html></html>'),
            new Response(200, ['Set-Cookie' => 'SATSESSID=sanitized; Path=/'], self::fixture('login-form.html')),
            new Response(200, [], self::fixture('saml.html')),
            new Response(200, [], self::fixture('authenticated-home.html')),
            new Response(200, [], self::fixture('communications-wrapper.html')),
            new Response(200, [], self::fixture('communications.html')),
            new Response(404, [], '<html><body>Ruta de salida no disponible</body></html>'),
            new Response(404, [], '<html><body>Ruta de salida no disponible</body></html>'),
            new Response(404, [], '<html><body>Ruta de salida no disponible</body></html>'),
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

        $result = Scraper::create($client, $resolver, 'AAA010101AAA', 'secret')->unreadCommunications();

        self::assertCount(3, $result);
        self::assertCount(9, $requests);
        $secondRequest = $requests[1];
        if (! $secondRequest instanceof RequestInterface) {
            self::fail('The second request was not recorded.');
        }
        self::assertTrue($secondRequest->hasHeader('Cookie'));
        self::assertSame('POST', $secondRequest->getMethod());
        self::assertSame('https://login.siat.sat.gob.mx/nidp/app/login', sprintf(
            '%s://%s%s',
            $secondRequest->getUri()->getScheme(),
            $secondRequest->getUri()->getHost(),
            $secondRequest->getUri()->getPath(),
        ));
        parse_str($secondRequest->getUri()->getQuery(), $loginQuery);
        self::assertSame('ptsc-ciec', $loginQuery['id'] ?? null);
        self::assertSame('1', $loginQuery['sid'] ?? null);
        self::assertSame('credential', $loginQuery['option'] ?? null);

        $loginRequest = $requests[2];
        if (! $loginRequest instanceof RequestInterface) {
            self::fail('The login request was not recorded.');
        }
        parse_str((string) $loginRequest->getBody(), $loginFields);
        self::assertSame('AAA010101AAA', $loginFields['Ecom_User_ID'] ?? null);
        self::assertSame('secret', $loginFields['Ecom_Password'] ?? null);
        self::assertSame('ABCDE', $loginFields['userCaptcha'] ?? null);
        self::assertSame('Enviar', $loginFields['submit'] ?? null);

        $requestedUris = [];
        foreach ($requests as $request) {
            $requestedUris[] = (string) $request->getUri();
        }
        $requestedUris = implode(' ', $requestedUris);
        self::assertStringContainsString('/iniciar-expediente/mis-comunicados/', $requestedUris);
        self::assertStringNotContainsString('mis-notificaciones', $requestedUris);
        self::assertStringContainsString('Common/Logic/COMMON_Logout', $requestedUris);
        self::assertStringContainsString('/app/seg/cerrarSesion', $requestedUris);
        self::assertStringContainsString('/nidp/app/plogout', $requestedUris);
    }
}
