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
use Tecnofy\BuzonTributarioSatScraper\Services\CommunicationParser;
use Tecnofy\BuzonTributarioSatScraper\Services\CommunicationService;
use Tecnofy\BuzonTributarioSatScraper\Tests\TestCase;

final class CommunicationServiceTest extends TestCase
{
    public function testRequestsCommunicationsPageWithoutOpeningAnyMessage(): void
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
        $service = new CommunicationService(
            new HttpRequester(new Client(['handler' => $stack])),
            new CommunicationParser(),
        );

        $communications = $service->collectUnread(new Page(
            self::fixture('authenticated-home.html'),
            'https://wwwmat.sat.gob.mx/buzon',
        ));

        self::assertCount(3, $communications);
        self::assertCount(1, $requests);
        $request = $requests[0];
        if (! $request instanceof RequestInterface) {
            self::fail('The request was not recorded.');
        }
        self::assertSame(
            'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-comunicados/',
            (string) $request->getUri(),
        );
    }

    public function testFollowsCommunicationsIframeFromAuthenticatedPage(): void
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
        $service = new CommunicationService(
            new HttpRequester(new Client(['handler' => $stack])),
            new CommunicationParser(),
        );

        $communications = $service->collectUnread(new Page(
            self::fixture('communications-wrapper.html'),
            'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-comunicados/',
        ));

        self::assertCount(3, $communications);
        self::assertCount(1, $requests);
        $request = $requests[0];
        if (! $request instanceof RequestInterface) {
            self::fail('The request was not recorded.');
        }
        self::assertSame(
            'https://aplicacionesc.mat.sat.gob.mx/WebComunicados/Comunicados.aspx',
            (string) $request->getUri(),
        );
        self::assertSame(
            'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-comunicados/',
            $request->getHeaderLine('Referer'),
        );
    }

    public function testRequestsWrapperAndThenFollowsCommunicationsIframe(): void
    {
        /** @var ArrayObject<int, RequestInterface> $requests */
        $requests = new ArrayObject();
        $mock = new MockHandler([
            new Response(200, [], self::fixture('communications-wrapper.html')),
            new Response(200, [], self::fixture('communications.html')),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::tap(
            static function (RequestInterface $request) use ($requests): void {
                $requests[] = $request;
            },
        ));
        $service = new CommunicationService(
            new HttpRequester(new Client(['handler' => $stack])),
            new CommunicationParser(),
        );

        $communications = $service->collectUnread(new Page(
            self::fixture('authenticated-home.html'),
            'https://wwwmat.sat.gob.mx/buzon',
        ));

        self::assertCount(3, $communications);
        self::assertCount(2, $requests);
        $wrapperRequest = $requests[0];
        $frameRequest = $requests[1];
        if (! $wrapperRequest instanceof RequestInterface || ! $frameRequest instanceof RequestInterface) {
            self::fail('The requests were not recorded.');
        }
        self::assertSame(
            'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-comunicados/',
            (string) $wrapperRequest->getUri(),
        );
        self::assertSame(
            'https://aplicacionesc.mat.sat.gob.mx/WebComunicados/Comunicados.aspx',
            (string) $frameRequest->getUri(),
        );
        self::assertSame(
            'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-comunicados/',
            $frameRequest->getHeaderLine('Referer'),
        );
    }
}
