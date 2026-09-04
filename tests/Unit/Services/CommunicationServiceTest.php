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
use Tecnofy\BuzonTributarioSatScraper\Exceptions\UnexpectedPageException;
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

    public function testUsesKnownFrameAsFallbackWhenWrapperDoesNotExposeIframe(): void
    {
        /** @var ArrayObject<int, RequestInterface> $requests */
        $requests = new ArrayObject();
        $mock = new MockHandler([
            new Response(200, [], '<html><body>&gt; Mis comunicados</body></html>'),
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
        $frameRequest = $requests[1];
        if (! $frameRequest instanceof RequestInterface) {
            self::fail('The frame request was not recorded.');
        }
        self::assertSame(
            'https://aplicacionesc.mat.sat.gob.mx/WebComunicados/Comunicados.aspx',
            (string) $frameRequest->getUri(),
        );
    }

    public function testUnexpectedFrameResponseReportsSafePageClassification(): void
    {
        $mock = new MockHandler([new Response(200, [], '<form><input name="userCaptcha"></form>')]);
        $service = new CommunicationService(
            new HttpRequester(new Client(['handler' => HandlerStack::create($mock)])),
            new CommunicationParser(),
        );

        try {
            $service->collectUnread(new Page(
                self::fixture('communications-wrapper.html'),
                'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-comunicados/',
            ));
            self::fail('The request should have failed.');
        } catch (UnexpectedPageException $exception) {
            self::assertSame(
                'The SAT communications iframe response at '
                    . 'https://aplicacionesc.mat.sat.gob.mx/WebComunicados/Comunicados.aspx '
                    . 'does not contain the expected headings (the SAT returned a login page).',
                $exception->getMessage(),
            );
        }
    }

    public function testCompletesSecondarySsoBeforeParsingCommunicationsIframe(): void
    {
        /** @var ArrayObject<int, RequestInterface> $requests */
        $requests = new ArrayObject();
        $mock = new MockHandler([
            new Response(200, [], self::fixture('saml-request.html')),
            new Response(200, [], self::fixture('saml-response-secondary.html')),
            new Response(200, [], self::fixture('saml-consumer.html')),
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
            self::fixture('communications-wrapper.html'),
            'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-comunicados/',
        ));

        self::assertCount(3, $communications);
        self::assertCount(4, $requests);
        $frameRequest = $requests[0];
        $samlRequest = $requests[1];
        $samlResponse = $requests[2];
        $consumerRequest = $requests[3];
        if (
            ! $frameRequest instanceof RequestInterface
            || ! $samlRequest instanceof RequestInterface
            || ! $samlResponse instanceof RequestInterface
            || ! $consumerRequest instanceof RequestInterface
        ) {
            self::fail('The secondary SSO requests were not recorded.');
        }
        self::assertSame('GET', $frameRequest->getMethod());
        self::assertSame('POST', $samlRequest->getMethod());
        self::assertSame('POST', $samlResponse->getMethod());
        self::assertSame('POST', $consumerRequest->getMethod());
        self::assertSame(
            'https://login.siat.sat.gob.mx/nidp/saml2/sso',
            (string) $samlRequest->getUri(),
        );
        self::assertSame(
            'https://loginc.mat.sat.gob.mx/nidp/saml2/sso',
            (string) $samlResponse->getUri(),
        );
        parse_str((string) $samlRequest->getBody(), $requestFields);
        self::assertSame('SANITIZED_REQUEST', $requestFields['SAMLRequest'] ?? null);
        parse_str((string) $samlResponse->getBody(), $responseFields);
        self::assertSame('SANITIZED_RESPONSE', $responseFields['SAMLResponse'] ?? null);
        parse_str((string) $consumerRequest->getBody(), $consumerFields);
        self::assertSame('SANITIZED_TARGET', $consumerFields['target'] ?? null);
    }
}
