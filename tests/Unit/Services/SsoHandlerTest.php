<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Tests\Unit\Services;

use ArrayObject;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;
use Tecnofy\BuzonTributarioSatScraper\Services\SsoHandler;
use Tecnofy\BuzonTributarioSatScraper\Tests\TestCase;

final class SsoHandlerTest extends TestCase
{
    public function testFollowsTrustedJavascriptRedirect(): void
    {
        /** @var ArrayObject<int, RequestInterface> $requests */
        $requests = new ArrayObject();
        $mock = new MockHandler([new Response(200, [], self::fixture('communications.html'))]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::tap(
            static function (RequestInterface $request) use ($requests): void {
                $requests[] = $request;
            },
        ));
        $handler = new SsoHandler(new HttpRequester(new Client(['handler' => $stack])));

        $page = $handler->handle(new Page(
            self::fixture('javascript-redirect.html'),
            'https://loginc.mat.sat.gob.mx/nidp/saml2/spassertion_consumer',
        ));

        self::assertStringContainsString('Mensajes no leídos', $page->html);
        self::assertCount(1, $requests);
        $request = $requests[0];
        if (! $request instanceof RequestInterface) {
            self::fail('The redirect request was not recorded.');
        }
        self::assertSame(
            'https://aplicacionesc.mat.sat.gob.mx/WebComunicados/Comunicados.aspx',
            (string) $request->getUri(),
        );
    }
}
